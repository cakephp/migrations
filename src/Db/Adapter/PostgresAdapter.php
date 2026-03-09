<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use Cake\Database\Connection;
use Cake\Database\Schema\TableSchema;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use InvalidArgumentException;
use Migrations\Db\AlterInstructions;
use Migrations\Db\InsertMode;
use Migrations\Db\Literal;
use Migrations\Db\Table\CheckConstraint;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\Partition;
use Migrations\Db\Table\PartitionDefinition;
use Migrations\Db\Table\TableMetadata;
use RuntimeException;

class PostgresAdapter extends AbstractAdapter
{
    /**
     * Maximum length for identifiers (table names, column names, constraint names, etc.)
     */
    protected const IDENTIFIER_MAX_LENGTH = 63;

    public const GENERATED_ALWAYS = 'ALWAYS';
    public const GENERATED_BY_DEFAULT = 'BY DEFAULT';
    /**
     * Allow insert when a column was created with the GENERATED ALWAYS clause.
     * This is required for seeding the database.
     */
    public const OVERRIDE_SYSTEM_VALUE = 'OVERRIDING SYSTEM VALUE';

    /**
     * @var string[]
     */
    protected static array $specificColumnTypes = [
        self::TYPE_JSON,
        self::PHINX_TYPE_JSONB,
        self::TYPE_CIDR,
        self::TYPE_INET,
        self::TYPE_MACADDR,
        self::TYPE_INTERVAL,
        self::TYPE_BINARY_UUID,
        self::TYPE_NATIVE_UUID,
    ];

    private const GIN_INDEX_TYPE = 'gin';

    /**
     * Columns with comments
     *
     * @var \Migrations\Db\Table\Column[]
     */
    protected array $columnsWithComments = [];

    /**
     * Use identity columns if available (Postgres >= 10.0)
     *
     * @var bool
     */
    protected bool $useIdentity;

    /**
     * {@inheritDoc}
     */
    public function setConnection(Connection $connection): AdapterInterface
    {
        // always set here since connect() isn't always called
        $version = $connection->getDriver()->version();
        $this->useIdentity = (float)$version >= 10;

        return parent::setConnection($connection);
    }

    /**
     * Quotes a schema name for use in a query.
     *
     * @param string $schemaName Schema Name
     * @return string
     */
    public function quoteSchemaName(string $schemaName): string
    {
        return $this->quoteColumnName($schemaName);
    }

    /**
     * @inheritDoc
     */
    public function quoteTableName(string $tableName): string
    {
        $parts = $this->getSchemaName($tableName);

        return $this->quoteSchemaName($parts['schema']) . '.' . $this->quoteColumnName($parts['table']);
    }

    /**
     * @inheritDoc
     */
    public function hasTable(string $tableName): bool
    {
        // Only use the cache in dry-run mode where tables aren't actually created.
        // In normal mode, always check the database to handle cases where tables
        // are dropped via execute() which doesn't update the cache.
        if ($this->isDryRunEnabled() && $this->hasCreatedTable($tableName)) {
            return true;
        }

        $parts = $this->getSchemaName($tableName);
        $tableName = $parts['table'];

        $dialect = $this->getSchemaDialect();

        return $dialect->hasTable($tableName, $parts['schema']);
    }

    /**
     * @inheritDoc
     */
    public function createTable(TableMetadata $table, array $columns = [], array $indexes = []): void
    {
        $queries = [];

        $options = $table->getOptions();
        $parts = $this->getSchemaName($table->getName());

         // Add the default primary key
        if (!isset($options['id']) || $options['id'] === true) {
            $options['id'] = 'id';
        }

        if (is_string($options['id'])) {
            // Handle id => "field_name" to support AUTO_INCREMENT
            $column = new Column();
            $column->setName($options['id'])
                   ->setType('integer')
                   ->setOptions(['identity' => true]);

            array_unshift($columns, $column);
            if (isset($options['primary_key']) && (array)$options['id'] !== (array)$options['primary_key']) {
                throw new InvalidArgumentException('You cannot enable an auto incrementing ID field and a primary key');
            }
            $options['primary_key'] = $options['id'];
        }

        // TODO - process table options like collation etc
        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteTableName($table->getName()) . ' (';

        $dialect = $this->getSchemaDialect();
        $this->columnsWithComments = [];
        foreach ($columns as $column) {
            $sql .= $dialect->columnDefinitionSql($this->mapColumnData($column->toArray())) . ', ';

            // set column comments, if needed
            if ($column->getComment()) {
                $this->columnsWithComments[] = $column;
            }
        }

         // set the primary key(s)
        if (isset($options['primary_key'])) {
            $sql = rtrim($sql);
            $sql .= sprintf(' CONSTRAINT %s PRIMARY KEY (', $this->quoteColumnName($parts['table'] . '_pkey'));
            if (is_string($options['primary_key'])) { // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($options['primary_key']);
            } elseif (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                $sql .= implode(',', array_map([$this, 'quoteColumnName'], $options['primary_key']));
            }
            $sql .= ')';
        } else {
            $sql = rtrim($sql, ', '); // no primary keys
        }

        $sql .= ')';

        // add partitioning clause
        $partition = $table->getPartition();
        if ($partition !== null) {
            $sql .= ' ' . $this->getPartitionSqlDefinition($partition);
        }

        $queries[] = $sql;

        // process column comments
        if ($this->columnsWithComments) {
            foreach ($this->columnsWithComments as $column) {
                $queries[] = $this->getColumnCommentSqlDefinition($column, $table->getName());
            }
        }

        // set the indexes
        if ($indexes) {
            foreach ($indexes as $index) {
                $queries[] = $this->getIndexSqlDefinition($index, $table->getName());
            }
        }

        // process table comments
        if (isset($options['comment'])) {
            $queries[] = sprintf(
                'COMMENT ON TABLE %s IS %s',
                $this->quoteTableName($table->getName()),
                $this->quoteString($options['comment']),
            );
        }

        // create partition tables for PostgreSQL declarative partitioning
        if ($partition !== null) {
            foreach ($partition->getDefinitions() as $definition) {
                $queries[] = $this->getPartitionTableSql($table->getName(), $partition, $definition);
            }
        }

        foreach ($queries as $query) {
            $this->execute($query);
        }

        $this->addCreatedTable($table->getName());
    }

    /**
     * Apply postgres specific translations between the values using migrations constants/types
     * and the cakephp/database constants. Over time, these can be aligned.
     *
     * @param array<string, mixed> $data The raw column data.
     * @return array<string, mixed> Modified column data.
     */
    protected function mapColumnData(array $data): array
    {
        if (
            $data['type'] === self::TYPE_TIMESTAMP &&
            isset($data['timezone']) && $data['timezone'] === true
        ) {
            $data['type'] = 'timestamptimezone';
        }
        // CakePHP only has a json type (which uses the JSONB storage type)
        if ($data['type'] === self::PHINX_TYPE_JSONB) {
            $data['type'] = 'json';
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getChangePrimaryKeyInstructions(TableMetadata $table, array|string|null $newColumns): AlterInstructions
    {
        $parts = $this->getSchemaName($table->getName());
        $instructions = new AlterInstructions();

        // Drop the existing primary key
        $primaryKey = $this->getPrimaryKey($table->getName());
        if (!empty($primaryKey['constraint'])) {
            $sql = sprintf(
                'DROP CONSTRAINT %s',
                $this->quoteColumnName($primaryKey['constraint']),
            );
            $instructions->addAlter($sql);
        }

        // Add the new primary key
        if ($newColumns) {
            $sql = sprintf(
                'ADD CONSTRAINT %s PRIMARY KEY (',
                $this->quoteColumnName($parts['table'] . '_pkey'),
            );
            if (is_string($newColumns)) { // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($newColumns);
            } else { // handle primary_key => array('tag_id', 'resource_id')
                $sql .= implode(',', array_map([$this, 'quoteColumnName'], $newColumns));
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
        $newComment = $newComment !== null
            ? $this->quoteString($newComment)
            : 'NULL';
        $sql = sprintf(
            'COMMENT ON TABLE %s IS %s',
            $this->quoteTableName($table->getName()),
            $newComment,
        );
        $instructions->addPostStep($sql);

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    protected function getRenameTableInstructions(string $tableName, string $newTableName): AlterInstructions
    {
        $this->updateCreatedTableName($tableName, $newTableName);
        $sql = sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($newTableName),
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
            'TRUNCATE TABLE %s RESTART IDENTITY',
            $this->quoteTableName($tableName),
        );

        $this->execute($sql);
    }

    /**
     * @inheritDoc
     */
    public function getColumns(string $tableName): array
    {
        $dialect = $this->getSchemaDialect();
        $columns = [];
        foreach ($dialect->describeColumns($tableName) as $columnInfo) {
            $column = new Column();
            $column->setName($columnInfo['name'])
                   ->setType($columnInfo['type'])
                   ->setNull($columnInfo['null'])
                   ->setDefault($columnInfo['default'])
                   ->setLimit($columnInfo['length'])
                   ->setScale($columnInfo['precision'] ?? null);

            if ($columnInfo['autoIncrement'] ?? false) {
                $column->setIdentity(true);
            }

            if ($this->useIdentity) {
                $column->setGenerated($columnInfo['generated'] ?? null);
            }

            if ($columnInfo['type'] === TableSchema::TYPE_TIMESTAMP_FRACTIONAL) {
                $column->setPrecision($columnInfo['precision'] ?? null);
            }
            if ($columnInfo['type'] === TableSchema::TYPE_TIMESTAMP_TIMEZONE) {
                $column->setTimezone(true);
            }
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @inheritDoc
     */
    protected function getAddColumnInstructions(TableMetadata $table, Column $column): AlterInstructions
    {
        $dialect = $this->getSchemaDialect();

        $instructions = new AlterInstructions();
        $instructions->addAlter(sprintf(
            'ADD %s',
            $dialect->columnDefinitionSql($this->mapColumnData($column->toArray())),
        ));

        if ($column->getComment()) {
            $instructions->addPostStep($this->getColumnCommentSqlDefinition($column, $table->getName()));
        }

        return $instructions;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getRenameColumnInstructions(
        string $tableName,
        string $columnName,
        string $newColumnName,
    ): AlterInstructions {
        $parts = $this->getSchemaName($tableName);
        $sql = 'SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS column_exists
             FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ?';
        $params = [
            $parts['schema'],
            $parts['table'],
            $columnName,
        ];
        $result = $this->query($sql, $params)->fetch('assoc');
        if (!$result || !(bool)$result['column_exists']) {
            throw new InvalidArgumentException("The specified column does not exist: $columnName");
        }

        $instructions = new AlterInstructions();
        $instructions->addPostStep(
            sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumnName),
            ),
        );

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    protected function getChangeColumnInstructions(
        string $tableName,
        string $columnName,
        Column $newColumn,
    ): AlterInstructions {
        $quotedColumnName = $this->quoteColumnName($columnName);
        $instructions = new AlterInstructions();
        if ($newColumn->getType() === 'boolean') {
            $sql = sprintf('ALTER COLUMN %s DROP DEFAULT', $quotedColumnName);
            $instructions->addAlter($sql);
        }
        $dialect = $this->getSchemaDialect();

        $columnSql = $dialect->columnDefinitionSql($this->mapColumnData($newColumn->toArray()));
        // Remove the column name from $columnSql
        $columnType = preg_replace('/^"?(?:[^"]+)"?\s+/', '', $columnSql);
        // Remove generated clause
        $columnType = preg_replace('/GENERATED (?:ALWAYS|BY DEFAULT) AS IDENTITY/', '', $columnType);

        $sql = sprintf(
            'ALTER COLUMN %s TYPE %s',
            $quotedColumnName,
            $columnType,
        );
        if (in_array($newColumn->getType(), ['smallinteger', 'integer', 'biginteger'], true)) {
            $sql .= sprintf(
                ' USING (%s::bigint)',
                $quotedColumnName,
            );
        }
        if (in_array($newColumn->getType(), ['uuid', 'nativeuuid', 'binaryuuid'])) {
            $sql .= sprintf(
                ' USING (%s::uuid)',
                $quotedColumnName,
            );
        }
        if (in_array($newColumn->getType(), ['json'])) {
            $sql .= sprintf(
                ' USING (%s::jsonb)',
                $quotedColumnName,
            );
        }
        // NULL and DEFAULT cannot be set while changing column type
        $sql = preg_replace('/ NOT NULL/', '', $sql);
        $sql = preg_replace('/ DEFAULT NULL/', '', $sql);
        // If it is set, DEFAULT is the last definition
        $sql = preg_replace('/DEFAULT .*/', '', $sql);
        if ($newColumn->getType() === 'boolean') {
            $sql .= sprintf(
                ' USING (CASE WHEN %s IS NULL THEN NULL WHEN %s::int=0 THEN FALSE ELSE TRUE END)',
                $quotedColumnName,
                $quotedColumnName,
            );
        }
        $instructions->addAlter($sql);

        $column = $this->getColumn($tableName, $columnName);
        assert($column !== null, 'Column must exist');

        if ($this->useIdentity) {
            // process identity
            $sql = sprintf(
                'ALTER COLUMN %s',
                $quotedColumnName,
            );
            if ($newColumn->isIdentity() && $newColumn->getGenerated() !== null) {
                if ($column->isIdentity()) {
                    $sql .= sprintf(' SET GENERATED %s', (string)$newColumn->getGenerated());
                } else {
                    $sql .= sprintf(' ADD GENERATED %s AS IDENTITY', (string)$newColumn->getGenerated());
                }
            } else {
                $sql .= ' DROP IDENTITY IF EXISTS';
            }
            $instructions->addAlter($sql);
        }

        // process null
        $sql = sprintf(
            'ALTER COLUMN %s',
            $quotedColumnName,
        );

        if (!$newColumn->getIdentity() && !$column->getIdentity() && $newColumn->isNull()) {
            $sql .= ' DROP NOT NULL';
        } else {
            $sql .= ' SET NOT NULL';
        }

        $instructions->addAlter($sql);

        if ($newColumn->getDefault() !== null) {
            $instructions->addAlter(sprintf(
                'ALTER COLUMN %s SET %s',
                $quotedColumnName,
                $this->getDefaultValueDefinition($newColumn->getDefault(), (string)$newColumn->getType()),
            ));
        } elseif (!$newColumn->getIdentity()) {
            //drop default
            $instructions->addAlter(sprintf(
                'ALTER COLUMN %s DROP DEFAULT',
                $quotedColumnName,
            ));
        }

        // rename column
        if ($columnName !== $newColumn->getName()) {
            $instructions->addPostStep(sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $this->quoteTableName($tableName),
                $quotedColumnName,
                $this->quoteColumnName((string)$newColumn->getName()),
            ));
        }

        // change column comment if needed
        if ($newColumn->getComment()) {
            $instructions->addPostStep($this->getColumnCommentSqlDefinition($newColumn, $tableName));
        }

        return $instructions;
    }

    /**
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return ?\Migrations\Db\Table\Column
     */
    protected function getColumn(string $tableName, string $columnName): ?Column
    {
        $columns = $this->getColumns($tableName);
        foreach ($columns as $column) {
            if ($column->getName() === $columnName) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    protected function getDropColumnInstructions(string $tableName, string $columnName): AlterInstructions
    {
        $alter = sprintf(
            'DROP COLUMN %s',
            $this->quoteColumnName($columnName),
        );

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
        $instructions->addPostStep($this->getIndexSqlDefinition($index, $table->getName()));

        return $instructions;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getDropIndexByColumnsInstructions(string $tableName, $columns): AlterInstructions
    {
        $parts = $this->getSchemaName($tableName);

        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

        $indexes = $this->getIndexes($tableName);
        foreach ($indexes as $index) {
            $a = array_diff($columns, $index['columns']);
            if (!$a) {
                return new AlterInstructions([], [sprintf(
                    'DROP INDEX IF EXISTS %s',
                    '"' . ($parts['schema'] . '".' . $this->quoteColumnName($index['name'])),
                )]);
            }
        }

        throw new InvalidArgumentException(sprintf(
            'The specified index on columns `%s` does not exist',
            implode(',', $columns),
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getDropIndexByNameInstructions(string $tableName, string $indexName): AlterInstructions
    {
        $parts = $this->getSchemaName($tableName);

        $sql = sprintf(
            'DROP INDEX IF EXISTS %s',
            '"' . ($parts['schema'] . '".' . $this->quoteColumnName($indexName)),
        );

        return new AlterInstructions([], [$sql]);
    }

    /**
     * @inheritDoc
     */
    public function hasPrimaryKey(string $tableName, $columns, ?string $constraint = null): bool
    {
        $primaryKey = $this->getPrimaryKey($tableName);
        if (!$primaryKey) {
            return false;
        }

        if ($constraint) {
            return $primaryKey['constraint'] === $constraint;
        } else {
            if (is_string($columns)) {
                $columns = [$columns]; // str to array
            }
            $missingColumns = array_diff($columns, $primaryKey['columns']);

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

        foreach ($indexes as $index) {
            if ($index['type'] === 'primary') {
                return $index;
            }
        }

        return ['constraint' => '', 'columns' => []];
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
            $this->getForeignKeySqlDefinition($foreignKey, $table->getName()),
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * @inheritDoc
     */
    protected function getDropForeignKeyInstructions($tableName, $constraint): AlterInstructions
    {
        $alter = sprintf(
            'DROP CONSTRAINT %s',
            $this->quoteColumnName($constraint),
        );

        return new AlterInstructions([$alter]);
    }

    /**
     * @inheritDoc
     */
    protected function getDropForeignKeyByColumnsInstructions(string $tableName, array $columns): AlterInstructions
    {
        $instructions = new AlterInstructions();

        $matches = [];
        $foreignKeys = $this->getForeignKeys($tableName);
        foreach ($foreignKeys as $key) {
            if ($key['columns'] === $columns) {
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
        $constraints = $dialect->describeCheckConstraints($tableName);

        return $constraints;
    }

    /**
     * @inheritDoc
     */
    protected function getAddCheckConstraintInstructions(TableMetadata $table, CheckConstraint $checkConstraint): AlterInstructions
    {
        $constraintName = $checkConstraint->getName();
        if ($constraintName === null) {
            // Auto-generate constraint name if not provided
            $parts = $this->getSchemaName($table->getName());
            $constraintName = $parts['table'] . '_chk_' . substr(md5($checkConstraint->getExpression()), 0, 8);
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
        $alter = sprintf(
            'DROP CONSTRAINT %s',
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
        $this->execute(sprintf(
            'CREATE DATABASE %s WITH ENCODING = %s',
            $this->quoteSchemaName($name),
            $this->quoteString($charset),
        ));
    }

    /**
     * @inheritDoc
     */
    public function hasDatabase(string $name): bool
    {
        $query = $this->getSelectBuilder();
        $query->select([$query->func()->count('*')])
            ->from('pg_database')
            ->where(['datname' => $name]);
        $result = $query->execute()->fetch('assoc');
        if (!$result) {
            return false;
        }

        return $result['count'] > 0;
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase($name): void
    {
        $this->disconnect();
        $this->execute(sprintf('DROP DATABASE IF EXISTS %s', $this->quoteSchemaName($name)));
        $this->createdTables = [];
        $this->connect();
    }

    /**
     * Gets the PostgreSQL Column Comment Definition for a column object.
     *
     * @param \Migrations\Db\Table\Column $column Column
     * @param string $tableName Table name
     * @return string
     */
    protected function getColumnCommentSqlDefinition(Column $column, string $tableName): string
    {
        $comment = (string)$column->getComment();
        // passing 'null' is to remove column comment
        $comment = strcasecmp($comment, 'NULL') !== 0
                 ? $this->quoteString($comment)
                 : 'NULL';

        return sprintf(
            'COMMENT ON COLUMN %s.%s IS %s;',
            $this->quoteTableName($tableName),
            $this->quoteColumnName((string)$column->getName()),
            $comment,
        );
    }

    /**
     * Gets the PostgreSQL Index Definition for an Index object.
     *
     * @param \Migrations\Db\Table\Index $index Index
     * @param string $tableName Table name
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index, string $tableName): string
    {
        $parts = $this->getSchemaName($tableName);
        $columnNames = (array)$index->getColumns();

        $indexName = $index->getName();
        if ($indexName === null || strlen($indexName) === 0) {
            $indexName = sprintf('%s_%s', $parts['table'], implode('_', $columnNames));
        }

        $order = $index->getOrder() ?? [];
        $columnNames = array_map(function ($columnName) use ($order) {
            $ret = '"' . $columnName . '"';
            if (isset($order[$columnName])) {
                $ret .= ' ' . $order[$columnName];
            }

            return $ret;
        }, $columnNames);

        $include = $index->getInclude();
        $includedColumns = $include ? sprintf(' INCLUDE ("%s")', implode('","', $include)) : '';

        $createIndexSentence = 'CREATE %sINDEX%s %s ON %s ';
        if ($index->getType() === self::GIN_INDEX_TYPE) {
            $createIndexSentence .= ' USING ' . $index->getType() . '(%s) %s;';
        } else {
            $createIndexSentence .= '(%s)%s%s;';
        }
        $where = (string)$index->getWhere();
        if ($where) {
            $where = ' WHERE ' . $where;
        }

        return sprintf(
            $createIndexSentence,
            $index->getType() === Index::UNIQUE ? 'UNIQUE ' : '',
            $index->getConcurrently() ? ' CONCURRENTLY' : '',
            $this->quoteColumnName((string)$indexName),
            $this->quoteTableName($tableName),
            implode(',', $columnNames),
            $includedColumns,
            $where,
        );
    }

    /**
     * Gets the MySQL Foreign Key Definition for an ForeignKey object.
     *
     * @param \Migrations\Db\Table\ForeignKey $foreignKey Foreign key
     * @param string $tableName Table name
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey, string $tableName): string
    {
        $constraintName = $foreignKey->getName() ?: $this->getUniqueForeignKeyName($tableName, $foreignKey->getColumns());
        $columnList = implode(', ', array_map($this->quoteColumnName(...), $foreignKey->getColumns()));
        $refColumnList = implode(', ', array_map($this->quoteColumnName(...), $foreignKey->getReferencedColumns()));
        $def = ' CONSTRAINT ' . $this->quoteColumnName($constraintName) .
        ' FOREIGN KEY (' . $columnList . ')' .
        ' REFERENCES ' . $this->quoteTableName($foreignKey->getReferencedTable()) . ' (' . $refColumnList . ')';
        if ($foreignKey->getOnDelete()) {
            $def .= " ON DELETE {$foreignKey->getOnDelete()}";
        }
        if ($foreignKey->getOnUpdate()) {
            $def .= " ON UPDATE {$foreignKey->getOnUpdate()}";
        }
        if ($foreignKey->getDeferrableMode()) {
            $def .= " {$foreignKey->getDeferrableMode()}";
        }

        return $def;
    }

    /**
     * Generate a unique foreign key constraint name.
     *
     * @param string $tableName Table name
     * @param array<string> $columns Column names
     * @return string
     */
    protected function getUniqueForeignKeyName(string $tableName, array $columns): string
    {
        $parts = $this->getSchemaName($tableName);
        $baseName = $parts['table'] . '_' . implode('_', $columns) . '_fkey';
        $maxLength = static::IDENTIFIER_MAX_LENGTH - 3;
        if (strlen($baseName) > $maxLength) {
            $baseName = substr($baseName, 0, $maxLength);
        }
        $existingKeys = $this->getForeignKeys($tableName);
        $existingNames = array_column($existingKeys, 'name');

        if (!in_array($baseName, $existingNames, true)) {
            return $baseName;
        }

        $counter = 2;
        while (in_array($baseName . '_' . $counter, $existingNames, true)) {
            $counter++;
        }

        return $baseName . '_' . $counter;
    }

    /**
     * @inheritDoc
     */
    public function createSchemaTable(): void
    {
        if ($this->hasSchema($this->getGlobalSchemaName()) === false) {
            $this->createSchema($this->getGlobalSchemaName());
        }

        $this->setSearchPath();

        parent::createSchemaTable();
    }

    /**
     * @inheritDoc
     */
    public function getVersions(): array
    {
        $this->setSearchPath();

        return parent::getVersions();
    }

    /**
     * @inheritDoc
     */
    public function getVersionLog(): array
    {
        $this->setSearchPath();

        return parent::getVersionLog();
    }

    /**
     * Creates the specified schema.
     *
     * @param string $schemaName Schema Name
     * @return void
     */
    public function createSchema(string $schemaName = 'public'): void
    {
        // from postgres 9.3 we can use "CREATE SCHEMA IF NOT EXISTS schema_name"
        $sql = sprintf('CREATE SCHEMA IF NOT EXISTS %s', $this->quoteSchemaName($schemaName));
        $this->execute($sql);
    }

    /**
     * Checks to see if a schema exists.
     *
     * @param string $schemaName Schema Name
     * @return bool
     */
    public function hasSchema(string $schemaName): bool
    {
        $query = $this->getSelectBuilder();
        $query->select([$query->func()->count('*')])
            ->from('pg_namespace')
            ->where(['nspname' => $schemaName]);

        $result = $query->execute()->fetch('assoc');
        if (!$result) {
            return false;
        }

        return $result['count'] > 0;
    }

    /**
     * Drops the specified schema table.
     *
     * @param string $schemaName Schema name
     * @return void
     */
    public function dropSchema(string $schemaName): void
    {
        $sql = sprintf('DROP SCHEMA IF EXISTS %s CASCADE', $this->quoteSchemaName($schemaName));
        $this->execute($sql);

        foreach ($this->createdTables as $idx => $createdTable) {
            if ($this->getSchemaName($createdTable)['schema'] === $this->quoteSchemaName($schemaName)) {
                unset($this->createdTables[$idx]);
            }
        }
    }

    /**
     * Drops all schemas.
     *
     * @return void
     */
    public function dropAllSchemas(): void
    {
        foreach ($this->getAllSchemas() as $schema) {
            $this->dropSchema($schema);
        }
    }

    /**
     * Returns schemas.
     *
     * @return array
     */
    public function getAllSchemas(): array
    {
        $query = $this->getSelectBuilder();
        $query->select(['schema_name'])
            ->from('information_schema.schemata')
            ->where([
                ['schema_name !=' => 'information_schema'],
                ['schema_name !~' => '^pg_'],
            ]);
        $items = $query->execute()->fetchAll('assoc');
        $schemaNames = [];
        foreach ($items as $item) {
            $schemaNames[] = $item['schema_name'];
        }

        return $schemaNames;
    }

    /**
     * @inheritDoc
     */
    public function getColumnTypes(): array
    {
        return array_merge(parent::getColumnTypes(), static::$specificColumnTypes);
    }

    /**
     * @inheritDoc
     */
    public function isValidColumnType(Column $column): bool
    {
        // If not a standard column type, maybe it is array type?
        return parent::isValidColumnType($column) || $this->isArrayType($column->getType());
    }

    /**
     * Check if the given column is an array of a valid type.
     *
     * @param string|\Migrations\Db\Literal $columnType Column type
     * @return bool
     */
    protected function isArrayType(string|Literal $columnType): bool
    {
        if (!preg_match('/^([a-z]+)(?:\[\]){1,}$/', (string)$columnType, $matches)) {
            return false;
        }

        $baseType = $matches[1];

        return in_array($baseType, $this->getColumnTypes(), true);
    }

    /**
     * @param string $tableName Table name
     * @return array
     */
    protected function getSchemaName(string $tableName): array
    {
        $schema = $this->getGlobalSchemaName();
        $table = $tableName;
        if (strpos($tableName, '.') !== false) {
            [$schema, $table] = explode('.', $tableName);
        }

        return [
            'schema' => $schema,
            'table' => $table,
        ];
    }

    /**
     * Gets the schema name.
     *
     * @return string
     */
    protected function getGlobalSchemaName(): string
    {
        $options = $this->getOptions();
        $config = $options['connection']->config() ?? [];

        return empty($config['schema']) ? 'public' : $config['schema'];
    }

    /**
     * @inheritDoc
     */
    public function castToBool($value): mixed
    {
        return (bool)$value ? 'TRUE' : 'FALSE';
    }

    /**
     * Sets search path of schemas to look through for a table
     *
     * @return void
     */
    public function setSearchPath(): void
    {
        $this->execute(
            sprintf(
                'SET search_path TO %s,"$user",public',
                $this->quoteSchemaName($this->getGlobalSchemaName()),
            ),
        );
    }

    /**
     * @inheritDoc
     */
    public function insert(
        TableMetadata $table,
        array $row,
        ?InsertMode $mode = null,
        ?array $updateColumns = null,
        ?array $conflictColumns = null,
    ): void {
        $sql = sprintf(
            'INSERT INTO %s ',
            $this->quoteTableName($table->getName()),
        );
        $columns = array_keys($row);
        $sql .= '(' . implode(', ', array_map($this->quoteColumnName(...), $columns)) . ')';

        foreach ($row as $column => $value) {
            if (is_bool($value)) {
                $row[$column] = $this->castToBool($value);
            }
        }

        $override = '';
        if ($this->useIdentity) {
            $override = self::OVERRIDE_SYSTEM_VALUE . ' ';
        }

        $conflictClause = $this->getConflictClause($mode, $updateColumns, $conflictColumns);

        if ($this->isDryRunEnabled()) {
            $sql .= ' ' . $override . 'VALUES (' . implode(', ', array_map($this->quoteValue(...), $row)) . ')' . $conflictClause . ';';
            $this->io->out($sql);
        } else {
            $values = [];
            $vals = [];
            foreach ($row as $value) {
                $placeholder = '?';
                if ($value instanceof Literal) {
                    $placeholder = (string)$value;
                }
                $values[] = $placeholder;
                if ($placeholder === '?') {
                    $vals[] = $value;
                }
            }
            $sql .= ' ' . $override . 'VALUES (' . implode(',', $values) . ')' . $conflictClause;
            $this->getConnection()->execute($sql, $vals);
        }
    }

    /**
     * @inheritDoc
     */
    public function bulkinsert(
        TableMetadata $table,
        array $rows,
        ?InsertMode $mode = null,
        ?array $updateColumns = null,
        ?array $conflictColumns = null,
    ): void {
        $sql = sprintf(
            'INSERT INTO %s ',
            $this->quoteTableName($table->getName()),
        );
        $current = current($rows);
        /** @var array<string> $keys */
        $keys = array_keys($current);

        $override = '';
        if ($this->useIdentity) {
            $override = self::OVERRIDE_SYSTEM_VALUE . ' ';
        }

        $sql .= '(' . implode(', ', array_map($this->quoteColumnName(...), $keys)) . ') ' . $override . 'VALUES ';

        $conflictClause = $this->getConflictClause($mode, $updateColumns, $conflictColumns);

        if ($this->isDryRunEnabled()) {
            $values = array_map(function ($row) {
                return '(' . implode(', ', array_map($this->quoteValue(...), $row)) . ')';
            }, $rows);
            $sql .= implode(', ', $values) . $conflictClause . ';';
            $this->io->out($sql);
        } else {
            $vals = [];
            $queries = [];
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $v) {
                    $placeholder = '?';
                    if ($v instanceof Literal) {
                        $placeholder = (string)$v;
                    }
                    $values[] = $placeholder;
                    if ($placeholder === '?') {
                        if ($v instanceof DateTime) {
                            $vals[] = $v->toDateTimeString();
                        } elseif ($v instanceof Date) {
                            $vals[] = $v->toDateString();
                        } elseif (is_bool($v)) {
                            $vals[] = $this->castToBool($v);
                        } else {
                            $vals[] = $v;
                        }
                    }
                }
                $query = '(' . implode(', ', $values) . ')';
                $queries[] = $query;
            }
            $sql .= implode(',', $queries) . $conflictClause;
            $this->getConnection()->execute($sql, $vals);
        }
    }

    /**
     * Get the ON CONFLICT clause based on insert mode.
     *
     * PostgreSQL requires explicit conflict columns to determine which unique constraint
     * should trigger the update. Unlike MySQL's ON DUPLICATE KEY UPDATE which applies
     * to all unique constraints, PostgreSQL's ON CONFLICT clause must specify the columns.
     *
     * @param \Migrations\Db\InsertMode|null $mode Insert mode
     * @param array<string>|null $updateColumns Columns to update on upsert conflict
     * @param array<string>|null $conflictColumns Columns that define uniqueness for upsert (required for PostgreSQL)
     * @return string
     * @throws \RuntimeException When using UPSERT mode without conflictColumns
     */
    protected function getConflictClause(
        ?InsertMode $mode = null,
        ?array $updateColumns = null,
        ?array $conflictColumns = null,
    ): string {
        if ($mode === InsertMode::IGNORE) {
            return ' ON CONFLICT DO NOTHING';
        }

        if ($mode === InsertMode::UPSERT) {
            if ($conflictColumns === null || $conflictColumns === []) {
                throw new RuntimeException(
                    'PostgreSQL requires the $conflictColumns parameter for insertOrUpdate(). ' .
                    'Specify the columns that have a unique constraint to determine conflict resolution.',
                );
            }
            $quotedConflictColumns = array_map($this->quoteColumnName(...), $conflictColumns);
            $updates = [];
            foreach ($updateColumns as $column) {
                $quotedColumn = $this->quoteColumnName($column);
                $updates[] = $quotedColumn . ' = EXCLUDED.' . $quotedColumn;
            }

            return ' ON CONFLICT (' . implode(', ', $quotedConflictColumns) . ') DO UPDATE SET ' . implode(', ', $updates);
        }

        return '';
    }

    /**
     * Gets the PostgreSQL Partition Definition SQL for CREATE TABLE.
     *
     * @param \Migrations\Db\Table\Partition $partition Partition configuration
     * @return string
     */
    protected function getPartitionSqlDefinition(Partition $partition): string
    {
        $type = $partition->getType();
        $columns = $partition->getColumns();

        if ($type === Partition::TYPE_KEY) {
            throw new RuntimeException('KEY partitioning is not supported in PostgreSQL');
        }

        // Build column list or expression
        if ($columns instanceof Literal) {
            $columnsSql = (string)$columns;
        } else {
            $columnsSql = implode(', ', array_map(fn($col) => $this->quoteColumnName($col), $columns));
        }

        return sprintf('PARTITION BY %s (%s)', $type, $columnsSql);
    }

    /**
     * Gets the SQL to create a partition table in PostgreSQL.
     *
     * @param string $tableName The parent table name
     * @param \Migrations\Db\Table\Partition $partition The partition configuration
     * @param \Migrations\Db\Table\PartitionDefinition $definition The partition definition
     * @return string
     */
    protected function getPartitionTableSql(string $tableName, Partition $partition, PartitionDefinition $definition): string
    {
        $partitionTableName = $definition->getTable() ?? ($tableName . '_' . $definition->getName());
        $type = $partition->getType();
        $value = $definition->getValue();

        $sql = sprintf(
            'CREATE TABLE %s PARTITION OF %s',
            $this->quoteTableName($partitionTableName),
            $this->quoteTableName($tableName),
        );

        if ($type === Partition::TYPE_RANGE) {
            $sql .= $this->getRangePartitionBounds($definition);
        } elseif ($type === Partition::TYPE_LIST) {
            $sql .= ' FOR VALUES IN (';
            if (is_array($value)) {
                $sql .= implode(', ', array_map(fn($v) => $this->quotePartitionValue($v), $value));
            } else {
                $sql .= $this->quotePartitionValue($value);
            }
            $sql .= ')';
        } elseif ($type === Partition::TYPE_HASH) {
            $count = $partition->getCount() ?? count($partition->getDefinitions());
            $index = array_search($definition, $partition->getDefinitions(), true);
            $sql .= sprintf(' FOR VALUES WITH (MODULUS %d, REMAINDER %d)', $count, $index);
        }

        if ($definition->getTablespace()) {
            $sql .= ' TABLESPACE ' . $this->quoteColumnName($definition->getTablespace());
        }

        return $sql;
    }

    /**
     * Get the RANGE partition bounds for PostgreSQL.
     *
     * @param \Migrations\Db\Table\PartitionDefinition $definition The partition definition
     * @return string
     */
    protected function getRangePartitionBounds(PartitionDefinition $definition): string
    {
        $value = $definition->getValue();

        // For RANGE, PostgreSQL uses FROM (value) TO (value) syntax
        // When MAXVALUE is used, we use MAXVALUE keyword
        if ($value === 'MAXVALUE') {
            return ' FOR VALUES FROM (MAXVALUE) TO (MAXVALUE)';
        }

        // Simple case: single value means upper bound, assume MINVALUE as lower
        if (!is_array($value) || !isset($value['from'])) {
            $upperBound = $this->quotePartitionValue($value);

            return sprintf(' FOR VALUES FROM (MINVALUE) TO (%s)', $upperBound);
        }

        // Explicit from/to
        $from = $value['from'];
        $to = $value['to'] ?? 'MAXVALUE';

        $fromSql = $from === 'MINVALUE' ? 'MINVALUE' : $this->quotePartitionValue($from);
        $toSql = $to === 'MAXVALUE' ? 'MAXVALUE' : $this->quotePartitionValue($to);

        return sprintf(' FOR VALUES FROM (%s) TO (%s)', $fromSql, $toSql);
    }

    /**
     * Quote a partition boundary value.
     *
     * @param mixed $value The value to quote
     * @return string
     */
    protected function quotePartitionValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if ($value === 'MINVALUE' || $value === 'MAXVALUE') {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        return $this->quoteString((string)$value);
    }

    /**
     * Get instructions for adding multiple partitions to an existing table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table
     * @param array<\Migrations\Db\Table\PartitionDefinition> $partitions The partitions to add
     * @return \Migrations\Db\AlterInstructions
     */
    protected function getAddPartitionsInstructions(TableMetadata $table, array $partitions): AlterInstructions
    {
        $instructions = new AlterInstructions();
        foreach ($partitions as $partition) {
            $instructions->merge($this->getAddPartitionSql($table, $partition));
        }

        return $instructions;
    }

    /**
     * Get instructions for adding a single partition to an existing table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table
     * @param \Migrations\Db\Table\PartitionDefinition $partition The partition to add
     * @return \Migrations\Db\AlterInstructions
     */
    private function getAddPartitionSql(TableMetadata $table, PartitionDefinition $partition): AlterInstructions
    {
        // PostgreSQL requires creating partition tables using CREATE TABLE ... PARTITION OF
        // This is more complex as we need the partition type info
        // For now, we'll create a basic RANGE partition
        $partitionTableName = $partition->getTable() ?? ($table->getName() . '_' . $partition->getName());
        $value = $partition->getValue();

        $sql = sprintf(
            'CREATE TABLE %s PARTITION OF %s',
            $this->quoteTableName($partitionTableName),
            $this->quoteTableName($table->getName()),
        );

        // Detect type based on value format
        if (is_array($value) && isset($value['from'])) {
            // Explicit RANGE
            $from = $value['from'] === 'MINVALUE' ? 'MINVALUE' : $this->quotePartitionValue($value['from']);
            $to = $value['to'] === 'MAXVALUE' ? 'MAXVALUE' : $this->quotePartitionValue($value['to']);
            $sql .= sprintf(' FOR VALUES FROM (%s) TO (%s)', $from, $to);
        } elseif (is_array($value)) {
            // LIST partition
            $sql .= ' FOR VALUES IN (';
            $sql .= implode(', ', array_map(fn($v) => $this->quotePartitionValue($v), $value));
            $sql .= ')';
        } else {
            // Simple RANGE (upper bound only)
            $sql .= sprintf(' FOR VALUES FROM (MINVALUE) TO (%s)', $this->quotePartitionValue($value));
        }

        if ($partition->getTablespace()) {
            $sql .= ' TABLESPACE ' . $this->quoteColumnName($partition->getTablespace());
        }

        return new AlterInstructions([], [$sql]);
    }

    /**
     * Get instructions for dropping multiple partitions from an existing table.
     *
     * @param string $tableName The table name
     * @param array<string> $partitionNames The partition names to drop
     * @return \Migrations\Db\AlterInstructions
     */
    protected function getDropPartitionsInstructions(string $tableName, array $partitionNames): AlterInstructions
    {
        $instructions = new AlterInstructions();
        foreach ($partitionNames as $partitionName) {
            $instructions->merge($this->getDropPartitionSql($tableName, $partitionName));
        }

        return $instructions;
    }

    /**
     * Get instructions for dropping a single partition from an existing table.
     *
     * @param string $tableName The table name
     * @param string $partitionName The partition name to drop
     * @return \Migrations\Db\AlterInstructions
     */
    private function getDropPartitionSql(string $tableName, string $partitionName): AlterInstructions
    {
        // In PostgreSQL, partitions are tables, so we drop the partition table
        // The partition name is typically the table_partitionname
        $partitionTableName = $tableName . '_' . $partitionName;

        // Use DETACH first (to preserve data) then DROP
        // For a complete drop without preserving data:
        $sql = sprintf('DROP TABLE IF EXISTS %s', $this->quoteTableName($partitionTableName));

        return new AlterInstructions([], [$sql]);
    }

    /**
     * Get the adapter type name
     *
     * @return string
     */
    public function getAdapterType(): string
    {
        // Hardcoded because the parent implementation
        // reads an option that is based off of Database\Driver
        // names which is postgres, but pgsql is required for
        // compatibility.
        return 'pgsql';
    }
}
