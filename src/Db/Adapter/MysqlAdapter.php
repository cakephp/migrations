<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Exception\QueryException;
use Cake\Database\Schema\SchemaDialect;
use Cake\Database\Schema\TableSchema;
use InvalidArgumentException;
use Migrations\Db\AlterInstructions;
use Migrations\Db\Table\CheckConstraint;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\TableMetadata;

/**
 * MySQL Adapter.
 */
class MysqlAdapter extends AbstractAdapter
{
    /**
     * @var string[]
     */
    protected static array $specificColumnTypes = [
        self::PHINX_TYPE_YEAR,
        self::PHINX_TYPE_JSON,
        self::PHINX_TYPE_BINARYUUID,
        self::PHINX_TYPE_ENUM,
        self::PHINX_TYPE_SET,
        self::PHINX_TYPE_BLOB,
        self::PHINX_TYPE_TINYBLOB,
        self::PHINX_TYPE_MEDIUMBLOB,
        self::PHINX_TYPE_LONGBLOB,
    ];

    /**
     * @deprecated 5.0.0 Enum column support will be removed in a future release.
     */
    public const PHINX_TYPE_ENUM = 'enum';
    /**
     * @deprecated 5.0.0 Set column support will be removed in a future release.
     */
    public const PHINX_TYPE_SET = 'set';
    /**
     * @deprecated 5.0.0 Use binary type with with no limit instead.
     */
    public const PHINX_TYPE_BLOB = 'blob';
    /**
     * @deprecated 5.0.0 Use binary type with with limit BLOB_SMALL instead.
     */
    public const PHINX_TYPE_TINYBLOB = 'tinyblob';
    /**
     * @deprecated 5.0.0 Use binary type with with limit BLOB_MEDIUM instead.
     */
    public const PHINX_TYPE_MEDIUMBLOB = 'mediumblob';
    /**
     * @deprecated 5.0.0 Use binary type with with limit BLOB_LONG instead.
     */
    public const PHINX_TYPE_LONGBLOB = 'longblob';
    /**
     * @deprecated 5.0.0 Use binary type instead.
     */
    public const PHINX_TYPE_VARBINARY = 'varbinary';

    // These constants roughly correspond to the maximum allowed value for each field,
    // except for the `_LONG` and `_BIG` variants, which are maxed at 32-bit
    // PHP_INT_MAX value. The `INT_REGULAR` field is just arbitrarily half of INT_BIG
    // as its actual value is its regular value is larger than PHP_INT_MAX. We do this
    // to keep consistent the type hints for Column::$limit being integers.
    public const TEXT_TINY = 255;
    public const TEXT_SMALL = 255; /* deprecated, alias of TEXT_TINY */
    /** @deprecated Use length of null instead **/
    public const TEXT_REGULAR = 65535;
    public const TEXT_MEDIUM = 16777215;
    public const TEXT_LONG = 2147483647;

    // According to https://dev.mysql.com/doc/refman/5.0/en/blob.html BLOB sizes are the same as TEXT
    public const BLOB_TINY = TableSchema::LENGTH_TINY;
    public const BLOB_SMALL = TableSchema::LENGTH_TINY; /* deprecated, alias of BLOB_TINY */
    public const BLOB_REGULAR = 65535;
    public const BLOB_MEDIUM = TableSchema::LENGTH_MEDIUM;
    public const BLOB_LONG = TableSchema::LENGTH_LONG;

    public const INT_TINY = 255;
    public const INT_SMALL = 65535;
    public const INT_MEDIUM = 16777215;
    public const INT_REGULAR = 1073741823;
    public const INT_BIG = 2147483647;

    public const INT_DISPLAY_TINY = 4;
    public const INT_DISPLAY_SMALL = 6;
    public const INT_DISPLAY_MEDIUM = 8;
    public const INT_DISPLAY_REGULAR = 11;
    public const INT_DISPLAY_BIG = 20;

    public const BIT = 64;

    public const TYPE_YEAR = 'year';

    public const FIRST = 'FIRST';

    /**
     * @inheritDoc
     */
    public function setConnection(Connection $connection): AdapterInterface
    {
        $connection->execute(sprintf('USE %s', $this->quoteTableName($this->getOption('database'))));

        return parent::setConnection($connection);
    }

    /**
     * @inheritDoc
     */
    public function quoteTableName(string $tableName): string
    {
        $driver = $this->getConnection()->getDriver();

        return $driver->quoteIdentifier($tableName);
    }

    /**
     * @inheritDoc
     */
    public function hasTable(string $tableName): bool
    {
        if ($this->hasCreatedTable($tableName)) {
            return true;
        }

        if (strpos($tableName, '.') !== false) {
            [$schema, $table] = explode('.', $tableName);
            $exists = $this->hasTableWithSchema($schema, $table);
            // Only break here on success, because it is possible for table names to contain a dot.
            if ($exists) {
                return true;
            }
        }

        $database = (string)$this->getOption('database');

        return $this->hasTableWithSchema($database, $tableName);
    }

    /**
     * @param string $schema The table schema
     * @param string $tableName The table name
     * @return bool
     */
    protected function hasTableWithSchema(string $schema, string $tableName): bool
    {
        $dialect = $this->getSchemaDialect();

        try {
            return $dialect->hasTable($tableName, $schema);
        } catch (QueryException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function createTable(TableMetadata $table, array $columns = [], array $indexes = []): void
    {
        // This method is based on the MySQL docs here: https://dev.mysql.com/doc/refman/5.1/en/create-index.html
        $defaultOptions = [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
        ];

        $options = array_merge(
            $defaultOptions,
            array_intersect_key($this->getOptions(), $defaultOptions),
            $table->getOptions(),
        );

        // Add the default primary key
        if (!isset($options['id']) || (isset($options['id']) && $options['id'] === true)) {
            $options['id'] = 'id';
        }

        if (isset($options['id']) && is_string($options['id'])) {
            $useUnsigned = (bool)Configure::read('Migrations.unsigned_primary_keys');
            // Handle id => "field_name" to support AUTO_INCREMENT
            $column = new Column();
            $column->setName($options['id'])
                   ->setType('integer')
                   ->setOptions([
                       'signed' => $options['signed'] ?? !$useUnsigned,
                       'identity' => true,
                   ]);

            if (isset($options['limit'])) {
                $column->setLimit($options['limit']);
            }

            array_unshift($columns, $column);
            if (isset($options['primary_key']) && (array)$options['id'] !== (array)$options['primary_key']) {
                throw new InvalidArgumentException('You cannot enable an auto incrementing ID field and a primary key');
            }
            $options['primary_key'] = $options['id'];
        }

        // open: process table options like collation etc

        // process table engine (default to InnoDB)
        $optionsStr = 'ENGINE = InnoDB';
        if (isset($options['engine'])) {
            $optionsStr = sprintf('ENGINE = %s', $options['engine']);
        }

        // process table collation
        if (isset($options['collation'])) {
            $charset = explode('_', $options['collation']);
            $optionsStr .= sprintf(' CHARACTER SET %s', $charset[0]);
            $optionsStr .= sprintf(' COLLATE %s', $options['collation']);
        }

        // set the table comment
        if (isset($options['comment'])) {
            $optionsStr .= sprintf(' COMMENT=%s ', $this->quoteString($options['comment']));
        }

        // set the table row format
        if (isset($options['row_format'])) {
            $optionsStr .= sprintf(' ROW_FORMAT=%s ', $options['row_format']);
        }

        $dialect = $this->getSchemaDialect();
        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteTableName($table->getName()) . ' (';
        foreach ($columns as $column) {
            $sql .= $this->columnDefinitionSql($dialect, $column) . ', ';
        }

        // set the primary key(s)
        if (isset($options['primary_key'])) {
            /** @var string|array $primaryKey */
            $primaryKey = $options['primary_key'];
            $sql = rtrim($sql);
            $sql .= ' PRIMARY KEY (';
            if (is_string($primaryKey)) { // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($primaryKey);
            } elseif (is_array($primaryKey)) { // handle primary_key => array('tag_id', 'resource_id')
                $sql .= implode(',', array_map($this->quoteColumnName(...), $primaryKey));
            }
            $sql .= ')';
        } else {
            $sql = substr(rtrim($sql), 0, -1); // no primary keys
        }

        // set the indexes
        foreach ($indexes as $index) {
            $sql .= ', ' . $this->getIndexSqlDefinition($index);
        }

        $sql .= ') ' . $optionsStr;
        $sql = rtrim($sql);

        // execute the sql
        $this->execute($sql);

        $this->addCreatedTable($table->getName());
    }

    /**
     * Apply MySQL specific translations between the values using migrations constants/types
     * and the cakephp/database constants. Over time, these can be aligned.
     *
     * @param array $data The raw column data.
     * @return array Modified column data.
     */
    protected function mapColumnData(array $data): array
    {
        if ($data['type'] == self::PHINX_TYPE_TEXT && $data['length'] !== null) {
            $data['length'] = match ($data['length']) {
                self::TEXT_LONG => TableSchema::LENGTH_LONG,
                self::TEXT_MEDIUM => TableSchema::LENGTH_MEDIUM,
                self::TEXT_REGULAR => null,
                self::TEXT_TINY => TableSchema::LENGTH_TINY,
                default => null,
            };
        }
        $blobTypes = [
            self::PHINX_TYPE_BINARY,
            self::PHINX_TYPE_VARBINARY,
            self::PHINX_TYPE_BLOB,
            self::PHINX_TYPE_TINYBLOB,
            self::PHINX_TYPE_MEDIUMBLOB,
            self::PHINX_TYPE_LONGBLOB,
        ];
        if (in_array($data['type'], $blobTypes, true)) {
            if ($data['length'] === self::BLOB_REGULAR) {
                $data['type'] = TableSchema::TYPE_BINARY;
                $data['length'] = null;
            }
            $standardLengths = [TableSchema::LENGTH_TINY, TableSchema::LENGTH_MEDIUM, TableSchema::LENGTH_LONG];
            if ($data['length'] !== null && !in_array($data['length'], $standardLengths, true)) {
                foreach ($standardLengths as $bucket) {
                    if ($bucket < $data['length']) {
                        continue;
                    }
                    $data['length'] = $bucket;
                    break;
                }
            }
            if ($data['length'] === null) {
                $data['length'] = match ($data['type']) {
                    self::PHINX_TYPE_TINYBLOB => TableSchema::LENGTH_TINY,
                    self::PHINX_TYPE_MEDIUMBLOB => TableSchema::LENGTH_MEDIUM,
                    self::PHINX_TYPE_LONGBLOB => TableSchema::LENGTH_LONG,
                    default => null,
                };
            }
            $data['type'] = 'binary';
        } elseif ($data['type'] === self::PHINX_TYPE_INTEGER) {
            if (isset($data['length']) && $data['length'] === self::INT_BIG) {
                $data['type'] = TableSchema::TYPE_BIGINTEGER;
                unset($data['length']);
            }
            unset($data['length']);
        }

        return $data;
    }

    /**
     * Get the SQL fragment for a column definition.
     *
     * This method provides backwards compatibility for enum and set types
     * as userland migrations use those types, but they are not supported
     * in cakephp/database.
     *
     * @param \Cake\Database\Schema\SchemaDialect $dialect The dialect to use.
     * @param \Migrations\Db\Table\Column $column The column to get the SQL for.
     * @return string
     */
    protected function columnDefinitionSql(SchemaDialect $dialect, Column $column): string
    {
        $columnData = $column->toArray();
        $deprecatedTypes = [self::PHINX_TYPE_ENUM, self::PHINX_TYPE_SET];
        if (in_array($columnData['type'], $deprecatedTypes, true)) {
            $sql = $this->quoteColumnName($columnData['name']) . ' ' . $columnData['type'];
            $values = $column->getValues();
            if ($values) {
                $sql .= '(' . implode(', ', array_map(function ($value) {
                    // Special case NULL to trigger errors as it isn't allowed
                    // in enum values.
                    return $value === null ? 'NULL' : $this->quoteString($value);
                }, $values)) . ')';
            }

            $sql .= $column->getEncoding() ? ' CHARACTER SET ' . $column->getEncoding() : '';
            $sql .= $column->getCollation() ? ' COLLATE ' . $column->getCollation() : '';
            $sql .= $column->isNull() ? ' NULL' : ' NOT NULL';
            $sql .= $column->getDefault() ? ' DEFAULT ' . $this->quoteString($column->getDefault()) : '';
            $sql .= $column->getComment() ? ' COMMENT ' . $this->quoteString($column->getComment()) : '';

            return $sql;
        }

        return $dialect->columnDefinitionSql($this->mapColumnData($columnData));
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getChangePrimaryKeyInstructions(TableMetadata $table, $newColumns): AlterInstructions
    {
        $instructions = new AlterInstructions();

        // Drop the existing primary key
        $primaryKey = $this->getPrimaryKey($table->getName());
        if (!empty($primaryKey['columns'])) {
            $instructions->addAlter('DROP PRIMARY KEY');
        }

        // Add the primary key(s)
        if ($newColumns) {
            $sql = 'ADD PRIMARY KEY (';
            if (is_string($newColumns)) { // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($newColumns);
            } elseif (is_array($newColumns)) { // handle primary_key => array('tag_id', 'resource_id')
                $sql .= implode(',', array_map($this->quoteColumnName(...), $newColumns));
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Invalid value for primary key: %s',
                    json_encode($newColumns),
                ));
            }
            $sql .= ')';
            $instructions->addAlter($sql);
        }

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    protected function getChangeCommentInstructions(TableMetadata $table, ?string $newComment): AlterInstructions
    {
        $instructions = new AlterInstructions();

        // passing 'null' is to remove table comment
        $newComment = $newComment ?? '';
        $sql = sprintf(' COMMENT=%s ', $this->quoteString($newComment));
        $instructions->addAlter($sql);

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    protected function getRenameTableInstructions(string $tableName, string $newTableName): AlterInstructions
    {
        $this->updateCreatedTableName($tableName, $newTableName);
        $sql = sprintf(
            'RENAME TABLE %s TO %s',
            $this->quoteTableName($tableName),
            $this->quoteTableName($newTableName),
        );

        return new AlterInstructions([], [$sql]);
    }

    /**
     * @inheritDoc
     */
    protected function getDropTableInstructions(string $tableName): AlterInstructions
    {
        $this->removeCreatedTable($tableName);
        $sql = sprintf('DROP TABLE %s', $this->quoteTableName($tableName));

        return new AlterInstructions([], [$sql]);
    }

    /**
     * @inheritDoc
     */
    public function truncateTable(string $tableName): void
    {
        $sql = sprintf(
            'TRUNCATE TABLE %s',
            $this->quoteTableName($tableName),
        );

        $this->execute($sql);
    }

    /**
     * Convert from cakephp/database conventions to migrations\column
     *
     * - converts datetimefractional -> datetime + length
     * - converts binary types to mysql blob type constants.
     *
     * @param array $columnData The cakephp/database column data to transform
     * @return array The extracted/converted type and length.
     */
    protected function mapColumnType(array $columnData): array
    {
        $type = $columnData['type'];
        $length = $columnData['length'];
        // Compatibility for precision
        if ($type === TableSchema::TYPE_DATETIME_FRACTIONAL) {
            $type = 'datetime';
            $length = $columnData['precision'] ?? $length;
        } elseif ($type === TableSchema::TYPE_TIMESTAMP_FRACTIONAL) {
            $type = 'timestamp';
            $length = $columnData['precision'] ?? $length;
        } elseif ($type === TableSchema::TYPE_BINARY) {
            // TODO could rawType be removed? We should be able to use the abstract type and length only.
            // CakePHP returns BLOB columns as 'binary' with specific lengths
            // Check the raw MySQL type to distinguish BLOB from BINARY columns
            $rawType = $columnData['rawType'] ?? '';
            if (str_contains($rawType, 'blob')) {
                // Map BLOB columns back to the appropriate BLOB types
                if (str_contains($rawType, 'tinyblob')) {
                    $type = static::PHINX_TYPE_TINYBLOB;
                    $length = static::BLOB_TINY;
                } elseif (str_contains($rawType, 'mediumblob')) {
                    $type = static::PHINX_TYPE_MEDIUMBLOB;
                    $length = static::BLOB_MEDIUM;
                } elseif (str_contains($rawType, 'longblob')) {
                    $type = static::PHINX_TYPE_LONGBLOB;
                    $length = static::BLOB_LONG;
                } else {
                    // Regular BLOB
                    $type = static::PHINX_TYPE_BLOB;
                    $length = static::BLOB_REGULAR;
                }
            }
            // else: keep as binary or varbinary (actual BINARY/VARBINARY column)
        }

        return [$type, $length];
    }

    /**
     * @inheritDoc
     */
    public function getColumns(string $tableName): array
    {
        $dialect = $this->getSchemaDialect();
        $columnRecords = $dialect->describeColumns($tableName);

        // Fetch raw column types to distinguish BLOB from BINARY columns
        $rawTypes = [];
        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM %s', $this->quoteTableName($tableName)));
        foreach ($rows as $row) {
            $rawTypes[$row['Field']] = strtolower($row['Type']);
        }

        $columns = [];
        foreach ($columnRecords as $record) {
            $record['rawType'] = $rawTypes[$record['name']] ?? null;
            [$type, $length] = $this->mapColumnType($record);

            $column = (new Column())
                ->setName($record['name'])
                ->setNull($record['null'])
                ->setType($type)
                ->setLimit($length)
                ->setDefault($record['default'])
                // cakephp uses precision not scale
                ->setScale($record['precision'] ?? null)
                ->setComment($record['comment']);

            if ($record['unsigned'] ?? false) {
                $column->setSigned(!$record['unsigned']);
            }
            if ($record['autoIncrement'] ?? false) {
                $column->setIdentity(true);
            }
            if ($record['onUpdate'] ?? false) {
                $column->setUpdate($record['onUpdate']);
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @inheritDoc
     */
    public function hasColumn(string $tableName, string $columnName): bool
    {
        $dialect = $this->getSchemaDialect();

        return $dialect->hasColumn($tableName, $columnName);
    }

    /**
     * @inheritDoc
     */
    protected function getAddColumnInstructions(TableMetadata $table, Column $column): AlterInstructions
    {
        $dialect = $this->getSchemaDialect();
        $alter = sprintf(
            'ADD %s',
            $this->columnDefinitionSql($dialect, $column),
        );

        $alter .= $this->afterClause($column);

        return new AlterInstructions([$alter]);
    }

    /**
     * Exposes the MySQL syntax to arrange a column `FIRST`.
     *
     * @param \Migrations\Db\Table\Column $column The column being altered.
     * @return string The appropriate SQL fragment.
     */
    protected function afterClause(Column $column): string
    {
        $after = $column->getAfter();
        if (!$after) {
            return '';
        }

        if ($after === self::FIRST) {
            return ' FIRST';
        }

        return ' AFTER ' . $this->quoteColumnName($after);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getRenameColumnInstructions(string $tableName, string $columnName, string $newColumnName): AlterInstructions
    {
        $columns = $this->getColumns($tableName);
        $targetColumn = null;

        foreach ($columns as $column) {
            if (strcasecmp($column->getName(), $columnName) === 0) {
                $targetColumn = $column;
                break;
            }
        }

        if ($targetColumn === null) {
            throw new InvalidArgumentException(sprintf(
                "The specified column doesn't exist: %s",
                $columnName,
            ));
        }

        // Fetch raw MySQL column info for the full definition string
        $rows = $this->fetchAll(sprintf('SHOW FULL COLUMNS FROM %s', $this->quoteTableName($tableName)));

        foreach ($rows as $row) {
            if (strcasecmp($row['Field'], $columnName) === 0) {
                $null = $row['Null'] === 'NO' ? 'NOT NULL' : 'NULL';
                $comment = isset($row['Comment']) ? ' COMMENT ' . '\'' . addslashes($row['Comment']) . '\'' : '';

                // create the extra string by also filtering out the DEFAULT_GENERATED option (MySQL 8 fix)
                $extras = array_filter(
                    explode(' ', strtoupper($row['Extra'])),
                    static function ($value) {
                        return $value !== 'DEFAULT_GENERATED';
                    },
                );
                $extra = ' ' . implode(' ', $extras);

                if (($row['Default'] !== null)) {
                    $columnType = $targetColumn->getType();
                    // Column::getType() can return string|Literal, but getDefaultValueDefinition expects string|null
                    $columnTypeName = is_string($columnType) ? $columnType : null;
                    $extra .= $this->getDefaultValueDefinition($row['Default'], $columnTypeName);
                }
                $definition = $row['Type'] . ' ' . $null . $extra . $comment;

                $alter = sprintf(
                    'CHANGE COLUMN %s %s %s',
                    $this->quoteColumnName($columnName),
                    $this->quoteColumnName($newColumnName),
                    $definition,
                );

                return new AlterInstructions([$alter]);
            }
        }

        throw new InvalidArgumentException(sprintf(
            "The specified column doesn't exist: %s",
            $columnName,
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getChangeColumnInstructions(string $tableName, string $columnName, Column $newColumn): AlterInstructions
    {
        $dialect = $this->getSchemaDialect();

        $alter = sprintf(
            'CHANGE %s %s%s',
            $this->quoteColumnName($columnName),
            $this->columnDefinitionSql($dialect, $newColumn),
            $this->afterClause($newColumn),
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * @inheritDoc
     */
    protected function getDropColumnInstructions(string $tableName, string $columnName): AlterInstructions
    {
        $alter = sprintf('DROP COLUMN %s', $this->quoteColumnName($columnName));

        return new AlterInstructions([$alter]);
    }

    /**
     * Get an array of indexes from a particular table.
     *
     * @param string $tableName Table name
     * @return array
     */
    protected function getIndexes(string $tableName): array
    {
        $dialect = $this->getSchemaDialect();
        $indexes = $dialect->describeIndexes($tableName);

        return $indexes;
    }

    /**
     * @inheritDoc
     */
    protected function getAddIndexInstructions(TableMetadata $table, Index $index): AlterInstructions
    {
        $instructions = new AlterInstructions();

        if ($index->getType() === Index::FULLTEXT) {
            // Must be executed separately
            // SQLSTATE[HY000]: General error: 1795 InnoDB presently supports one FULLTEXT index creation at a time
            $alter = sprintf(
                'ALTER TABLE %s ADD %s',
                $this->quoteTableName($table->getName()),
                $this->getIndexSqlDefinition($index),
            );

            $instructions->addPostStep($alter);
        } else {
            $alter = sprintf(
                'ADD %s',
                $this->getIndexSqlDefinition($index),
            );

            $instructions->addAlter($alter);
        }

        return $instructions;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getDropIndexByColumnsInstructions(string $tableName, $columns): AlterInstructions
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

        $indexes = $this->getIndexes($tableName);
        $columns = array_map('strtolower', $columns);

        foreach ($indexes as $index) {
            if ($columns == $index['columns']) {
                return new AlterInstructions([sprintf(
                    'DROP INDEX %s',
                    $this->quoteColumnName($index['name']),
                )]);
            }
        }

        throw new InvalidArgumentException(sprintf(
            'The specified index on columns `%s` does not exist',
            implode(',', $columns),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getDropIndexByNameInstructions(string $tableName, $indexName): AlterInstructions
    {
        $indexes = $this->getIndexes($tableName);

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return new AlterInstructions([sprintf(
                    'DROP INDEX %s',
                    $this->quoteColumnName($indexName),
                )]);
            }
        }

        throw new InvalidArgumentException(sprintf(
            'The specified index name `%s` does not exist',
            $indexName,
        ));
    }

    /**
     * @inheritDoc
     */
    public function hasPrimaryKey(string $tableName, string|array $columns, ?string $constraint = null): bool
    {
        $primaryKey = $this->getPrimaryKey($tableName);

        if (empty($primaryKey['name'])) {
            return false;
        }

        if ($constraint) {
            return $primaryKey['name'] === $constraint;
        } else {
            $missingColumns = array_diff((array)$columns, (array)$primaryKey['columns']);

            return empty($missingColumns);
        }
    }

    /**
     * Get the primary key from a particular table.
     *
     * @param string $tableName Table name
     * @return array
     */
    public function getPrimaryKey(string $tableName): array
    {
        $indexes = $this->getIndexes($tableName);
        $primaryKey = [
            'name' => '',
            'columns' => [],
        ];
        foreach ($indexes as $index) {
            if ($index['type'] === TableSchema::CONSTRAINT_PRIMARY) {
                $primaryKey = $index;
                break;
            }
        }

        return $primaryKey;
    }

    /**
     * Get an array of foreign keys from a particular table.
     *
     * @param string $tableName Table name
     * @return array
     */
    protected function getForeignKeys(string $tableName): array
    {
        $dialect = $this->getSchemaDialect();
        $foreignKeys = $dialect->describeForeignKeys($tableName);

        return $foreignKeys;
    }

    /**
     * @inheritDoc
     */
    protected function getAddForeignKeyInstructions(TableMetadata $table, ForeignKey $foreignKey): AlterInstructions
    {
        $alter = sprintf(
            'ADD %s',
            $this->getForeignKeySqlDefinition($foreignKey),
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * @inheritDoc
     */
    protected function getDropForeignKeyInstructions(string $tableName, string $constraint): AlterInstructions
    {
        $alter = sprintf(
            'DROP FOREIGN KEY %s',
            $constraint,
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getDropForeignKeyByColumnsInstructions(string $tableName, array $columns): AlterInstructions
    {
        $instructions = new AlterInstructions();

        $columns = array_map('mb_strtolower', $columns);

        $matches = [];
        $foreignKeys = $this->getForeignKeys($tableName);
        foreach ($foreignKeys as $key) {
            if (array_map('mb_strtolower', $key['columns']) === $columns) {
                $matches[] = $key['name'];
            }
        }

        if (!$matches) {
            throw new InvalidArgumentException(sprintf(
                'No foreign key on column(s) `%s` exists',
                implode(', ', $columns),
            ));
        }

        foreach ($matches as $name) {
            $instructions->merge(
                $this->getDropForeignKeyInstructions($tableName, $name),
            );
        }

        return $instructions;
    }

    /**
     * Get an array of check constraints from a particular table.
     *
     * @param string $tableName Table name
     * @return array
     */
    protected function getCheckConstraints(string $tableName): array
    {
        $dialect = $this->getSchemaDialect();

        return $dialect->describeCheckConstraints($tableName);
    }

    /**
     * @inheritDoc
     */
    protected function getAddCheckConstraintInstructions(TableMetadata $table, CheckConstraint $checkConstraint): AlterInstructions
    {
        $constraintName = $checkConstraint->getName();
        if ($constraintName === null) {
            // Auto-generate constraint name if not provided
            $constraintName = $table->getName() . '_chk_' . substr(md5($checkConstraint->getExpression()), 0, 8);
        }

        $alter = sprintf(
            'ADD CONSTRAINT %s CHECK (%s)',
            $this->quoteColumnName($constraintName),
            $checkConstraint->getExpression(),
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * @inheritDoc
     */
    protected function getDropCheckConstraintInstructions(string $tableName, string $constraintName): AlterInstructions
    {
        // MariaDB uses DROP CONSTRAINT, MySQL uses DROP CHECK
        $keyword = $this->isMariaDb() ? 'CONSTRAINT' : 'CHECK';

        $alter = sprintf(
            'DROP %s %s',
            $keyword,
            $this->quoteColumnName($constraintName),
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * @inheritDoc
     */
    public function createDatabase(string $name, array $options = []): void
    {
        $charset = $options['charset'] ?? 'utf8';

        if (isset($options['collation'])) {
            $this->execute(sprintf(
                'CREATE DATABASE %s DEFAULT CHARACTER SET `%s` COLLATE `%s`',
                $this->quoteTableName($name),
                $charset,
                $options['collation'],
            ));
        } else {
            $this->execute(sprintf('CREATE DATABASE %s DEFAULT CHARACTER SET `%s`', $this->quoteTableName($name), $charset));
        }
        $this->execute(sprintf('USE %s', $this->quoteTableName($name)));
    }

    /**
     * @inheritDoc
     */
    public function hasDatabase(string $name): bool
    {
        $query = $this->getSelectBuilder()
            ->select(['SCHEMA_NAME'])
            ->from('INFORMATION_SCHEMA.SCHEMATA')
            ->where(['SCHEMA_NAME' => $name]);

        $rows = $query->execute()->fetchAll('assoc');
        foreach ($rows as $row) {
            if ($row) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase(string $name): void
    {
        $this->execute(sprintf('DROP DATABASE IF EXISTS %s', $this->quoteTableName($name)));
        $this->createdTables = [];
    }

    /**
     * Gets the MySQL Index Definition for an Index object.
     *
     * @param \Migrations\Db\Table\Index $index Index
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index): string
    {
        $def = '';
        $limit = '';

        if ($index->getType() === Index::UNIQUE) {
            $def .= ' UNIQUE';
        }

        if ($index->getType() === Index::FULLTEXT) {
            $def .= ' FULLTEXT';
        }

        $def .= ' KEY';

        $name = $index->getName();
        if (is_string($name)) {
            $def .= ' ' . $this->quoteColumnName($name);
        }

        $columnNames = (array)$index->getColumns();
        $order = $index->getOrder() ?? [];
        $columnNames = array_map(function ($columnName) use ($order) {
            $ret = $this->quoteColumnName($columnName);
            if (isset($order[$columnName])) {
                $ret .= ' ' . $order[$columnName];
            }

            return $ret;
        }, $columnNames);

        if (!is_array($index->getLimit())) {
            if ($index->getLimit()) {
                $limit = '(' . $index->getLimit() . ')';
            }
            $def .= ' (' . implode(',', $columnNames) . $limit . ')';
        } else {
            $columns = (array)$index->getColumns();
            $limits = $index->getLimit();
            $def .= ' (';
            foreach ($columns as $column) {
                $limit = !isset($limits[$column]) || $limits[$column] <= 0 ? '' : '(' . $limits[$column] . ')';
                $columnSort = $order[$column] ?? '';
                $def .= $this->quoteColumnName($column) . $limit . ' ' . $columnSort . ', ';
            }
            $def = rtrim($def, ', ');
            $def .= ' )';
        }

        return $def;
    }

    /**
     * Gets the MySQL Foreign Key Definition for an ForeignKey object.
     *
     * @param \Migrations\Db\Table\ForeignKey $foreignKey Foreign key
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey): string
    {
        $def = '';
        if ($foreignKey->getName()) {
            $def .= ' CONSTRAINT ' . $this->quoteColumnName((string)$foreignKey->getName());
        }
        $columnNames = [];
        foreach ($foreignKey->getColumns() as $column) {
            $columnNames[] = $this->quoteColumnName($column);
        }
        $def .= ' FOREIGN KEY (' . implode(',', $columnNames) . ')';
        $refColumnNames = [];
        foreach ($foreignKey->getReferencedColumns() as $column) {
            $refColumnNames[] = $this->quoteColumnName($column);
        }
        $def .= ' REFERENCES ' . $this->quoteTableName($foreignKey->getReferencedTable()->getName()) . ' (' . implode(',', $refColumnNames) . ')';
        $onDelete = $foreignKey->getOnDelete();
        if ($onDelete) {
            $def .= ' ON DELETE ' . $onDelete;
        }
        $onUpdate = $foreignKey->getOnUpdate();
        if ($onUpdate) {
            $def .= ' ON UPDATE ' . $onUpdate;
        }

        return $def;
    }

    /**
     * Returns MySQL column types (inherited and MySQL specified).
     *
     * @return string[]
     */
    public function getColumnTypes(): array
    {
        $types = array_merge(parent::getColumnTypes(), static::$specificColumnTypes);

        if ($this->hasNativeUuid()) {
            $types[] = self::PHINX_TYPE_NATIVEUUID;
        }

        return $types;
    }

    /**
     * Get the default encoding for the current database.
     *
     * @return string The default encoding
     */
    public function getDefaultCollation(): string
    {
        $connection = $this->getConnection();
        $connectionConfig = $connection->config();

        $query = $this->getSelectBuilder()
            ->select(['DEFAULT_COLLATION_NAME'])
            ->from('INFORMATION_SCHEMA.SCHEMATA')
            ->where(['SCHEMA_NAME' => $connectionConfig['database']]);
        $row = $query->execute()->fetch('assoc');

        return $row['DEFAULT_COLLATION_NAME'] ?? '';
    }

    /**
     * Whether the server has a native uuid type.
     * (MariaDB 10.7.0+)
     *
     * @return bool
     */
    protected function hasNativeUuid(): bool
    {
        // Prevent infinite connect() loop when MysqlAdapter is used as a stub.
        if ($this->connection === null || !$this->getOption('connection')) {
            return false;
        }
        $connection = $this->getConnection();
        $version = $connection->getDriver()->version();

        return version_compare($version, '10.7', '>=');
    }

    /**
     * Whether the server is MariaDB (as opposed to MySQL).
     *
     * @return bool
     */
    protected function isMariaDb(): bool
    {
        // Prevent infinite connect() loop when MysqlAdapter is used as a stub.
        if ($this->connection === null || !$this->getOption('connection')) {
            return false;
        }
        $connection = $this->getConnection();
        $version = $connection->getDriver()->version();

        return stripos($version, 'mariadb') !== false;
    }
}
