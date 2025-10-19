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
use Migrations\Db\Literal;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\Table;

/**
 * Phinx MySQL Adapter.
 */
class MysqlAdapter extends AbstractAdapter
{
    /**
     * @var string[]
     */
    protected static array $specificColumnTypes = [
        self::PHINX_TYPE_ENUM,
        self::PHINX_TYPE_SET,
        self::PHINX_TYPE_YEAR,
        self::PHINX_TYPE_JSON,
        self::PHINX_TYPE_BINARYUUID,
        self::PHINX_TYPE_TINYBLOB,
        self::PHINX_TYPE_MEDIUMBLOB,
        self::PHINX_TYPE_LONGBLOB,
        self::PHINX_TYPE_MEDIUM_INTEGER,
    ];

    /**
     * @var bool[]
     */
    protected array $signedColumnTypes = [
        self::PHINX_TYPE_INTEGER => true,
        self::PHINX_TYPE_TINY_INTEGER => true,
        self::PHINX_TYPE_SMALL_INTEGER => true,
        self::PHINX_TYPE_MEDIUM_INTEGER => true,
        self::PHINX_TYPE_BIG_INTEGER => true,
        self::PHINX_TYPE_FLOAT => true,
        self::PHINX_TYPE_DECIMAL => true,
        self::PHINX_TYPE_DOUBLE => true,
        self::PHINX_TYPE_BOOLEAN => true,
    ];

    // These constants roughly correspond to the maximum allowed value for each field,
    // except for the `_LONG` and `_BIG` variants, which are maxed at 32-bit
    // PHP_INT_MAX value. The `INT_REGULAR` field is just arbitrarily half of INT_BIG
    // as its actual value is its regular value is larger than PHP_INT_MAX. We do this
    // to keep consistent the type hints for getSqlType and Column::$limit being integers.
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
        [$query, $params] = $dialect->listTablesSql(['database' => $schema]);

        try {
            $statement = $this->query($query, $params);
        } catch (QueryException $e) {
            return false;
        }
        $tables = [];
        foreach ($statement->fetchAll() as $row) {
            $tables[] = $row[0];
        }

        return in_array($tableName, $tables, true);
    }

    /**
     * @inheritDoc
     */
    public function createTable(Table $table, array $columns = [], array $indexes = []): void
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
        $binaryTypes = [
            self::PHINX_TYPE_BLOB,
            self::PHINX_TYPE_TINYBLOB,
            self::PHINX_TYPE_MEDIUMBLOB,
            self::PHINX_TYPE_LONGBLOB,
            self::PHINX_TYPE_VARBINARY,
            self::PHINX_TYPE_BINARY,
        ];
        if (in_array($data['type'], $binaryTypes, true)) {
            if (!isset($data['length'])) {
                $data['length'] = match ($data['type']) {
                    self::PHINX_TYPE_TINYBLOB => TableSchema::LENGTH_TINY,
                    self::PHINX_TYPE_MEDIUMBLOB => TableSchema::LENGTH_MEDIUM,
                    self::PHINX_TYPE_LONGBLOB => TableSchema::LENGTH_LONG,
                    default => $data['length'],
                };
            }
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
            $data['type'] = 'binary';
        } elseif ($data['type'] === self::PHINX_TYPE_INTEGER) {
            if (isset($data['length']) && $data['length'] === self::INT_BIG) {
                $data['type'] = TableSchema::TYPE_BIGINTEGER;
                unset($data['length']);
            }
            unset($data['length']);
        } elseif ($data['type'] == self::PHINX_TYPE_DOUBLE) {
            $data['type'] = TableSchema::TYPE_FLOAT;
            $data['length'] = 52;
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
    protected function getChangePrimaryKeyInstructions(Table $table, $newColumns): AlterInstructions
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
    protected function getChangeCommentInstructions(Table $table, ?string $newComment): AlterInstructions
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
        $rows = $this->fetchAll(sprintf('SHOW COLUMNS FROM %s', $this->quoteTableName($tableName)));
        foreach ($rows as $column) {
            if (strcasecmp($column['Field'], $columnName) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function getAddColumnInstructions(Table $table, Column $column): AlterInstructions
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
                    $phinxTypeInfo = $this->getPhinxType($row['Type']);
                    $extra .= $this->getDefaultValueDefinition($row['Default'], $phinxTypeInfo['name']);
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
    public function hasIndex(string $tableName, string|array $columns): bool
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

        $columns = array_map('strtolower', $columns);
        $indexes = $this->getIndexes($tableName);

        foreach ($indexes as $index) {
            if ($columns == $index['columns']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function hasIndexByName(string $tableName, string $indexName): bool
    {
        $indexes = $this->getIndexes($tableName);

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function getAddIndexInstructions(Table $table, Index $index): AlterInstructions
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
     * @inheritDoc
     */
    public function hasForeignKey(string $tableName, $columns, ?string $constraint = null): bool
    {
        $foreignKeys = $this->getForeignKeys($tableName);
        $names = array_map(fn($key) => $key['name'], $foreignKeys);
        if ($constraint) {
            return in_array($constraint, $names, true);
        }

        $columns = array_map('mb_strtolower', (array)$columns);

        foreach ($foreignKeys as $key) {
            if (array_map('mb_strtolower', $key['columns']) === $columns) {
                return true;
            }
        }

        return false;
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
    protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey): AlterInstructions
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
     * {@inheritDoc}
     *
     * @throws \Migrations\Db\Adapter\UnsupportedColumnTypeException
     */
    public function getSqlType(Literal|string $type, ?int $limit = null): array
    {
        $type = (string)$type;
        switch ($type) {
            case static::PHINX_TYPE_FLOAT:
            case static::PHINX_TYPE_DOUBLE:
            case static::PHINX_TYPE_DECIMAL:
            case static::PHINX_TYPE_DATE:
            case static::PHINX_TYPE_ENUM:
            case static::PHINX_TYPE_SET:
            case static::PHINX_TYPE_JSON:
            // Geospatial database types
            case static::PHINX_TYPE_GEOMETRY:
            case static::PHINX_TYPE_POINT:
            case static::PHINX_TYPE_LINESTRING:
            case static::PHINX_TYPE_POLYGON:
                return ['name' => $type];
            case static::PHINX_TYPE_DATETIME:
            case static::PHINX_TYPE_TIMESTAMP:
            case static::PHINX_TYPE_TIME:
                return ['name' => $type, 'limit' => $limit];
            case static::PHINX_TYPE_STRING:
                return ['name' => 'varchar', 'limit' => $limit ?: 255];
            case static::PHINX_TYPE_CHAR:
                return ['name' => 'char', 'limit' => $limit ?: 255];
            case static::PHINX_TYPE_TEXT:
                if ($limit) {
                    $sizes = [
                        // Order matters! Size must always be tested from longest to shortest!
                        'longtext' => static::TEXT_LONG,
                        'mediumtext' => static::TEXT_MEDIUM,
                        'text' => static::TEXT_REGULAR,
                        'tinytext' => static::TEXT_SMALL,
                    ];
                    foreach ($sizes as $name => $length) {
                        if ($limit >= $length) {
                            return ['name' => $name];
                        }
                    }
                }

                return ['name' => 'text'];
            case static::PHINX_TYPE_BINARY:
                if ($limit === null) {
                    $limit = 255;
                }

                if ($limit > 255) {
                    return $this->getSqlType(static::PHINX_TYPE_BLOB, $limit);
                }

                return ['name' => 'binary', 'limit' => $limit];
            case static::PHINX_TYPE_BINARYUUID:
                return ['name' => 'binary', 'limit' => 16];
            case static::PHINX_TYPE_VARBINARY:
                if ($limit === null) {
                    $limit = 255;
                }

                if ($limit > 255) {
                    return $this->getSqlType(static::PHINX_TYPE_BLOB, $limit);
                }

                return ['name' => 'varbinary', 'limit' => $limit];
            case static::PHINX_TYPE_BLOB:
                if ($limit !== null) {
                    // Rework this part as the chosen types were always UNDER the required length
                    $sizes = [
                        'tinyblob' => static::BLOB_SMALL,
                        'blob' => static::BLOB_REGULAR,
                        'mediumblob' => static::BLOB_MEDIUM,
                    ];

                    foreach ($sizes as $name => $length) {
                        if ($limit <= $length) {
                            return ['name' => $name];
                        }
                    }

                    // For more length requirement, the longblob is used
                    return ['name' => 'longblob'];
                }

                // If not limit is provided, fallback on blob
                return ['name' => 'blob'];
            case static::PHINX_TYPE_TINYBLOB:
                // Automatically reprocess blob type to ensure that correct blob subtype is selected given provided limit
                return $this->getSqlType(static::PHINX_TYPE_BLOB, $limit ?: static::BLOB_TINY);
            case static::PHINX_TYPE_MEDIUMBLOB:
                // Automatically reprocess blob type to ensure that correct blob subtype is selected given provided limit
                return $this->getSqlType(static::PHINX_TYPE_BLOB, $limit ?: static::BLOB_MEDIUM);
            case static::PHINX_TYPE_LONGBLOB:
                // Automatically reprocess blob type to ensure that correct blob subtype is selected given provided limit
                return $this->getSqlType(static::PHINX_TYPE_BLOB, $limit ?: static::BLOB_LONG);
            case static::PHINX_TYPE_BIT:
                return ['name' => 'bit', 'limit' => $limit ?: 64];
            case static::PHINX_TYPE_BIG_INTEGER:
                if ($limit === static::INT_BIG) {
                    $limit = static::INT_DISPLAY_BIG;
                }

                return ['name' => 'bigint', 'limit' => $limit ?: 20];
            case static::PHINX_TYPE_MEDIUM_INTEGER:
                if ($limit === static::INT_MEDIUM) {
                    $limit = static::INT_DISPLAY_MEDIUM;
                }

                return ['name' => 'mediumint', 'limit' => $limit ?: 8];
            case static::PHINX_TYPE_SMALL_INTEGER:
                if ($limit === static::INT_SMALL) {
                    $limit = static::INT_DISPLAY_SMALL;
                }

                return ['name' => 'smallint', 'limit' => $limit ?: 6];
            case static::PHINX_TYPE_TINY_INTEGER:
                if ($limit === static::INT_TINY) {
                    $limit = static::INT_DISPLAY_TINY;
                }

                return ['name' => 'tinyint', 'limit' => $limit ?: 4];
            case static::PHINX_TYPE_INTEGER:
                if ($limit && $limit >= static::INT_TINY) {
                    $sizes = [
                        // Order matters! Size must always be tested from longest to shortest!
                        'bigint' => static::INT_BIG,
                        'int' => static::INT_REGULAR,
                        'mediumint' => static::INT_MEDIUM,
                        'smallint' => static::INT_SMALL,
                        'tinyint' => static::INT_TINY,
                    ];
                    $limits = [
                        'tinyint' => static::INT_DISPLAY_TINY,
                        'smallint' => static::INT_DISPLAY_SMALL,
                        'mediumint' => static::INT_DISPLAY_MEDIUM,
                        'int' => static::INT_DISPLAY_REGULAR,
                        'bigint' => static::INT_DISPLAY_BIG,
                    ];
                    foreach ($sizes as $name => $length) {
                        if ($limit >= $length) {
                            $def = ['name' => $name];
                            if (isset($limits[$name])) {
                                $def['limit'] = $limits[$name];
                            }

                            return $def;
                        }
                    }
                } elseif (!$limit) {
                    $limit = static::INT_DISPLAY_REGULAR;
                }

                return ['name' => 'int', 'limit' => $limit];
            case static::PHINX_TYPE_BOOLEAN:
                return ['name' => 'tinyint', 'limit' => 1];
            case static::PHINX_TYPE_UUID:
                return ['name' => 'char', 'limit' => 36];
            case static::PHINX_TYPE_NATIVEUUID:
                if (!$this->hasNativeUuid()) {
                    throw new UnsupportedColumnTypeException(
                        'Column type "' . $type . '" is not supported by this version of MySQL.',
                    );
                }

                return ['name' => 'uuid'];
            case static::PHINX_TYPE_YEAR:
                if (!$limit || in_array($limit, [2, 4])) {
                    $limit = 4;
                }

                return ['name' => 'year', 'limit' => $limit];
            default:
                throw new UnsupportedColumnTypeException('Column type "' . $type . '" is not supported by MySQL.');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @internal param string $sqlType SQL type
     * @param string $sqlTypeDef SQL Type definition
     * @throws \Migrations\Db\Adapter\UnsupportedColumnTypeException
     * @return array Phinx type
     */
    public function getPhinxType(string $sqlTypeDef): array
    {
        $matches = [];
        if (!preg_match('/^([\w]+)(\(([\d]+)*(,([\d]+))*\))*(.+)*$/', $sqlTypeDef, $matches)) {
            throw new UnsupportedColumnTypeException('Column type "' . $sqlTypeDef . '" is not supported by MySQL.');
        }

        $limit = null;
        $scale = null;
        $type = $matches[1];
        if (count($matches) > 2) {
            $limit = $matches[3] ? (int)$matches[3] : null;
        }
        if (count($matches) > 4) {
            $scale = (int)$matches[5];
        }
        if ($type === 'tinyint' && $limit === 1) {
            $type = static::PHINX_TYPE_BOOLEAN;
            $limit = null;
        }
        switch ($type) {
            case 'varchar':
                $type = static::PHINX_TYPE_STRING;
                if ($limit === 255) {
                    $limit = null;
                }
                break;
            case 'char':
                $type = static::PHINX_TYPE_CHAR;
                if ($limit === 255) {
                    $limit = null;
                }
                if ($limit === 36) {
                    $type = static::PHINX_TYPE_UUID;
                }
                break;
            case 'tinyint':
                $type = static::PHINX_TYPE_TINY_INTEGER;
                break;
            case 'smallint':
                $type = static::PHINX_TYPE_SMALL_INTEGER;
                break;
            case 'mediumint':
                $type = static::PHINX_TYPE_MEDIUM_INTEGER;
                break;
            case 'int':
                $type = static::PHINX_TYPE_INTEGER;
                break;
            case 'bigint':
                $type = static::PHINX_TYPE_BIG_INTEGER;
                break;
            case 'bit':
                $type = static::PHINX_TYPE_BIT;
                if ($limit === 64) {
                    $limit = null;
                }
                break;
            case 'blob':
                $type = static::PHINX_TYPE_BLOB;
                $limit = static::BLOB_REGULAR;
                break;
            case 'tinyblob':
                $type = static::PHINX_TYPE_TINYBLOB;
                $limit = static::BLOB_TINY;
                break;
            case 'mediumblob':
                $type = static::PHINX_TYPE_MEDIUMBLOB;
                $limit = static::BLOB_MEDIUM;
                break;
            case 'longblob':
                $type = static::PHINX_TYPE_LONGBLOB;
                $limit = static::BLOB_LONG;
                break;
            case 'tinytext':
                $type = static::PHINX_TYPE_TEXT;
                $limit = static::TEXT_TINY;
                break;
            case 'mediumtext':
                $type = static::PHINX_TYPE_TEXT;
                $limit = static::TEXT_MEDIUM;
                break;
            case 'longtext':
                $type = static::PHINX_TYPE_TEXT;
                $limit = static::TEXT_LONG;
                break;
            case 'binary':
                if ($limit === null) {
                    $limit = 255;
                }

                if ($limit > 255) {
                    $type = static::PHINX_TYPE_BLOB;
                    break;
                }

                if ($limit === 16) {
                    $type = static::PHINX_TYPE_BINARYUUID;
                }
                break;
            case 'uuid':
                $type = static::PHINX_TYPE_NATIVEUUID;
                $limit = null;
                break;
        }

        try {
            // Call this to check if parsed type is supported.
            $this->getSqlType($type, $limit);
        } catch (UnsupportedColumnTypeException $e) {
            $type = Literal::from($type);
        }

        $phinxType = [
            'name' => $type,
            'limit' => $limit,
            'scale' => $scale,
        ];

        if ($type === static::PHINX_TYPE_ENUM || $type === static::PHINX_TYPE_SET) {
            $values = trim($matches[6], '()');
            $phinxType['values'] = [];
            $opened = false;
            $escaped = false;
            $wasEscaped = false;
            $value = '';
            $valuesLength = strlen($values);
            for ($i = 0; $i < $valuesLength; $i++) {
                $char = $values[$i];
                if ($char === "'" && !$opened) {
                    $opened = true;
                } elseif (
                    !$escaped
                    && ($i + 1) < $valuesLength
                    && (
                        $char === "'" && $values[$i + 1] === "'"
                        || $char === '\\' && $values[$i + 1] === '\\'
                    )
                ) {
                    $escaped = true;
                } elseif ($char === "'" && $opened && !$escaped) {
                    $phinxType['values'][] = $value;
                    $value = '';
                    $opened = false;
                } elseif (($char === "'" || $char === '\\') && $opened && $escaped) {
                    $value .= $char;
                    $escaped = false;
                    $wasEscaped = true;
                } elseif ($opened) {
                    if ($values[$i - 1] === '\\' && !$wasEscaped) {
                        if ($char === 'n') {
                            $char = "\n";
                        } elseif ($char === 'r') {
                            $char = "\r";
                        } elseif ($char === 't') {
                            $char = "\t";
                        }
                        if ($values[$i] !== $char) {
                            $value = substr($value, 0, strlen($value) - 1);
                        }
                    }
                    $value .= $char;
                    $wasEscaped = false;
                }
            }
        }

        return $phinxType;
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
        $rows = $this->query(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$name],
        )->fetchAll('assoc');

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
}
