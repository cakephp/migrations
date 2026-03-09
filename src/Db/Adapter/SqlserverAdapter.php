<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use BadMethodCallException;
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
use Migrations\Db\Table\TableMetadata;
use Migrations\MigrationInterface;

/**
 * Migrations SqlServer Adapter.
 */
class SqlserverAdapter extends AbstractAdapter
{
    /**
     * Maximum length for identifiers (table names, column names, constraint names, etc.)
     */
    protected const IDENTIFIER_MAX_LENGTH = 128;

    /**
     * @var string[]
     */
    protected static array $specificColumnTypes = [
        self::TYPE_BINARY_UUID,
        self::TYPE_NATIVE_UUID,
    ];

    /**
     * @var string
     */
    protected string $schema = 'dbo';

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
        $dialect = $this->getSchemaDialect();

        return $dialect->hasTable($parts['table'], $parts['schema']);
    }

    /**
     * @inheritDoc
     */
    public function createTable(TableMetadata $table, array $columns = [], array $indexes = []): void
    {
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

        $dialect = $this->getSchemaDialect();
        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteTableName($table->getName()) . ' (';
        $sqlBuffer = [];
        $columnsWithComments = [];
        foreach ($columns as $column) {
            $sqlBuffer[] = $dialect->columnDefinitionSql($column->toArray());

            // set column comments, if needed
            if ($column->getComment()) {
                $columnsWithComments[] = $column;
            }
        }

        // set the primary key(s)
        if (isset($options['primary_key'])) {
            $pkSql = sprintf('CONSTRAINT PK_%s PRIMARY KEY (', $parts['table']);
            /** @var string|array $primaryKey */
            $primaryKey = $options['primary_key'];

            if (is_string($primaryKey)) { // handle primary_key => 'id'
                $pkSql .= $this->quoteColumnName($primaryKey);
            } elseif (is_array($primaryKey)) { // handle primary_key => array('tag_id', 'resource_id')
                $pkSql .= implode(',', array_map($this->quoteColumnName(...), $primaryKey));
            }
            $pkSql .= ')';
            $sqlBuffer[] = $pkSql;
        }

        $sql .= implode(', ', $sqlBuffer);
        $sql .= ');';

        // process column comments
        foreach ($columnsWithComments as $column) {
            $sql .= $this->getColumnCommentSqlDefinition($column, $table->getName());
        }

        // set the indexes
        foreach ($indexes as $index) {
            $sql .= $this->getIndexSqlDefinition($index, $table->getName());
        }

        // execute the sql
        $this->execute($sql);

        $this->addCreatedTable($table->getName());
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
        if (!empty($primaryKey['constraint'])) {
            $sql = sprintf(
                'DROP CONSTRAINT %s',
                $this->quoteColumnName($primaryKey['constraint']),
            );
            $instructions->addAlter($sql);
        }

        // Add the primary key(s)
        if ($newColumns) {
            $sql = sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s PRIMARY KEY (',
                $this->quoteTableName($table->getName()),
                $this->quoteColumnName('PK_' . $table->getName()),
            );
            if (is_string($newColumns)) { // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($newColumns);
            } elseif (is_array($newColumns)) { // handle primary_key => array('tag_id', 'resource_id')
                $sql .= implode(',', array_map($this->quoteColumnName(...), $newColumns));
            }
            $sql .= ')';
            $instructions->addPostStep($sql);
        }

        return $instructions;
    }

    /**
     * @inheritDoc
     *
     * SqlServer does not implement this functionality, and so will always throw an exception if used.
     * @throws \BadMethodCallException
     */
    protected function getChangeCommentInstructions(TableMetadata $table, ?string $newComment): AlterInstructions
    {
        throw new BadMethodCallException('SqlServer does not have table comments');
    }

    /**
     * Gets the SqlServer column comment definition for a column object.
     *
     * @param \Migrations\Db\Table\Column $column Column
     * @param ?string $tableName Table name
     * @return string
     */
    protected function getColumnCommentSqlDefinition(Column $column, ?string $tableName): string
    {
        // passing 'null' is to remove column comment
        $currentComment = $this->getColumnComment((string)$tableName, $column->getName());

        $comment = strcasecmp((string)$column->getComment(), 'NULL') !== 0 ? $this->quoteString((string)$column->getComment()) : '\'\'';
        $command = $currentComment === null ? 'sp_addextendedproperty' : 'sp_updateextendedproperty';

        return sprintf(
            "EXECUTE %s N'MS_Description', N%s, N'SCHEMA', N'%s', N'TABLE', N'%s', N'COLUMN', N'%s';",
            $command,
            $comment,
            $this->schema,
            (string)$tableName,
            (string)$column->getName(),
        );
    }

    /**
     * @inheritDoc
     */
    protected function getRenameTableInstructions(string $tableName, string $newTableName): AlterInstructions
    {
        $this->updateCreatedTableName($tableName, $newTableName);
        $sql = sprintf(
            'EXEC sp_rename %s, %s',
            $this->quoteString($tableName),
            $this->quoteString($newTableName),
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
     * @param string $tableName Table name
     * @param ?string $columnName Column name
     * @return string|null
     */
    public function getColumnComment(string $tableName, ?string $columnName): ?string
    {
        $sql = "SELECT cast(extended_properties.[value] as nvarchar(4000)) comment
  FROM sys.schemas
 INNER JOIN sys.tables
    ON schemas.schema_id = tables.schema_id
 INNER JOIN sys.columns
    ON tables.object_id = columns.object_id
 INNER JOIN sys.extended_properties
    ON tables.object_id = extended_properties.major_id
   AND columns.column_id = extended_properties.minor_id
   AND extended_properties.name = 'MS_Description'
   WHERE schemas.[name] = ? AND tables.[name] = ? AND columns.[name] = ?";
        $params = [$this->schema, $tableName, (string)$columnName];
        $row = $this->query($sql, $params)->fetch('assoc');

        if ($row) {
            return trim($row['comment']);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getColumns(string $tableName): array
    {
        $dialect = $this->getSchemaDialect();

        $columns = [];
        foreach ($dialect->describeColumns($tableName) as $columnInfo) {
            $column = (new Column())
                ->setName($columnInfo['name'])
                ->setType($columnInfo['type'])
                ->setNull($columnInfo['null'])
                ->setLimit($columnInfo['length'])
                ->setDefault($this->parseDefault($columnInfo['default']))
                ->setComment($columnInfo['comment']);

            if ($columnInfo['autoIncrement'] ?? false) {
                $column->setIdentity($columnInfo['autoIncrement']);
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @param string|null $default Default
     * @return int|string|null
     */
    protected function parseDefault(?string $default): int|string|null
    {
        // if a column is non-nullable and has no default, the value of column_default is null,
        // otherwise it should be a string value that we parse below, including "(NULL)" which
        // also stands for a null default
        if ($default === null) {
            return null;
        }

        $result = preg_replace(["/\('(.*)'\)/", "/\(\((.*)\)\)/", "/\((.*)\)/"], '$1', $default);

        if (strtoupper($result) === 'NULL') {
            $result = null;
        } elseif (is_numeric($result)) {
            $result = (int)$result;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function getAddColumnInstructions(TableMetadata $table, Column $column): AlterInstructions
    {
        $dialect = $this->getSchemaDialect();
        $alter = sprintf(
            'ALTER TABLE %s ADD %s',
            $table->getName(),
            $dialect->columnDefinitionSql($column->toArray()),
        );

        return new AlterInstructions([], [$alter]);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function getRenameColumnInstructions(string $tableName, string $columnName, string $newColumnName): AlterInstructions
    {
        if (!$this->hasColumn($tableName, $columnName)) {
            throw new InvalidArgumentException("The specified column does not exist: $columnName");
        }

        $instructions = new AlterInstructions();

        $oldConstraintName = "DF_{$tableName}_{$columnName}";
        $newConstraintName = "DF_{$tableName}_{$newColumnName}";
        $sql = sprintf(
            'IF (OBJECT_ID(%s, \'D\') IS NOT NULL)
BEGIN
     EXECUTE sp_rename %s, %s, N\'OBJECT\'
END',
            $this->quoteString($oldConstraintName),
            $this->quoteString($oldConstraintName),
            $this->quoteString($newConstraintName),
        );
        $instructions->addPostStep($sql);

        $instructions->addPostStep(sprintf(
            'EXECUTE sp_rename %s, %s, N\'COLUMN\'',
            $this->quoteString($tableName . '.' . $columnName),
            $this->quoteString($newColumnName),
        ));

        return $instructions;
    }

    /**
     * Returns the instructions to change a column default value
     *
     * @param string $tableName The table where the column is
     * @param \Migrations\Db\Table\Column $newColumn The column to alter
     * @return \Migrations\Db\AlterInstructions
     */
    protected function getChangeDefault(string $tableName, Column $newColumn): AlterInstructions
    {
        $constraintName = "DF_{$tableName}_{$newColumn->getName()}";
        $default = $newColumn->getDefault();
        $instructions = new AlterInstructions();

        if ($default === null) {
            $default = 'DEFAULT NULL';
        } else {
            $default = ltrim($this->getDefaultValueDefinition($default, (string)$newColumn->getType()));
        }

        if (!$default) {
            return $instructions;
        }

        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s %s FOR %s',
            $this->quoteTableName($tableName),
            $constraintName,
            $default,
            $this->quoteColumnName((string)$newColumn->getName()),
        ));

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    protected function getChangeColumnInstructions(string $tableName, string $columnName, Column $newColumn): AlterInstructions
    {
        $columns = $this->getColumns($tableName);
        $oldColumn = null;
        foreach ($columns as $column) {
            if ($column->getName() === $columnName) {
                $oldColumn = $column;
                break;
            }
        }
        if ($oldColumn === null) {
            throw new InvalidArgumentException("Unknown column {$columnName} cannot be changed.");
        }

        $changeDefault =
            $newColumn->getDefault() !== $oldColumn->getDefault() ||
            $newColumn->getType() !== $oldColumn->getType();

        $instructions = new AlterInstructions();
        $dialect = $this->getSchemaDialect();

        if ($columnName !== $newColumn->getName()) {
            $instructions->merge(
                $this->getRenameColumnInstructions($tableName, $columnName, (string)$newColumn->getName()),
            );
        }

        if ($changeDefault) {
            $instructions->merge($this->getDropDefaultConstraint($tableName, (string)$newColumn->getName()));
        }

        // Sqlserver doesn't support defaults
        $columnData = $newColumn->toArray();
        unset($columnData['default']);

        $alterColumn = sprintf(
            'ALTER TABLE %s ALTER COLUMN %s',
            $this->quoteTableName($tableName),
            $dialect->columnDefinitionSql($columnData),
        );
        $alterColumn = preg_replace('/DEFAULT NULL/', '', $alterColumn);
        $instructions->addPostStep($alterColumn);

        // change column comment if needed
        if ($newColumn->getComment()) {
            $instructions->addPostStep($this->getColumnCommentSqlDefinition($newColumn, $tableName));
        }

        if ($changeDefault) {
            $instructions->merge($this->getChangeDefault($tableName, $newColumn));
        }

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    protected function getDropColumnInstructions(string $tableName, string $columnName): AlterInstructions
    {
        $instructions = $this->getDropDefaultConstraint($tableName, $columnName);

        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($columnName),
        ));

        return $instructions;
    }

    /**
     * @param string $tableName Table name
     * @param string|null $columnName Column name
     * @return \Migrations\Db\AlterInstructions
     */
    protected function getDropDefaultConstraint(string $tableName, ?string $columnName): AlterInstructions
    {
        $defaultConstraint = $this->getDefaultConstraint($tableName, (string)$columnName);

        if (!$defaultConstraint) {
            return new AlterInstructions();
        }

        return $this->getDropForeignKeyInstructions($tableName, $defaultConstraint);
    }

    /**
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return string|false
     */
    protected function getDefaultConstraint(string $tableName, string $columnName): string|false
    {
        $sql = "SELECT default_constraints.name
        FROM sys.all_columns
        INNER JOIN sys.tables ON all_columns.object_id = tables.object_id
        INNER JOIN sys.schemas ON tables.schema_id = schemas.schema_id
        INNER JOIN sys.default_constraints ON all_columns.default_object_id = default_constraints.object_id
        WHERE schemas.name = 'dbo' AND tables.name = ? AND all_columns.name = ?";

        $rows = $this->query($sql, [$tableName, $columnName])->fetchAll('assoc');

        return empty($rows) ? false : $rows[0]['name'];
    }

    /**
     * @param string $tableId Table ID
     * @param string $indexId Index ID
     * @return array
     */
    protected function getIndexColumns(string $tableId, string $indexId): array
    {
        $sql = 'SELECT AC.[name] AS [column_name]
FROM sys.[index_columns] IC
  INNER JOIN sys.[all_columns] AC ON IC.[column_id] = AC.[column_id]
WHERE AC.[object_id] = ? AND IC.[index_id] = ?  AND IC.[object_id] = ?
ORDER BY IC.[key_ordinal]';

        $params = [$tableId, $indexId, $tableId];
        $rows = $this->query($sql, $params)->fetchAll('assoc');
        $columns = [];
        foreach ($rows as $row) {
            $columns[] = strtolower($row['column_name']);
        }

        return $columns;
    }

    /**
     * Get an array of indexes from a particular table.
     *
     * @param string $tableName Table name
     * @return array
     */
    public function getIndexes(string $tableName): array
    {
        $dialect = $this->getSchemaDialect();

        return $dialect->describeIndexes($tableName);
    }

    /**
     * @inheritDoc
     */
    protected function getAddIndexInstructions(TableMetadata $table, Index $index): AlterInstructions
    {
        $sql = $this->getIndexSqlDefinition($index, $table->getName());

        return new AlterInstructions([], [$sql]);
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
        $instructions = new AlterInstructions();

        foreach ($indexes as $index) {
            $a = array_diff($columns, $index['columns']);
            if (!$a) {
                $instructions->addPostStep(sprintf(
                    'DROP INDEX %s ON %s',
                    $this->quoteColumnName($index['name']),
                    $this->quoteTableName($tableName),
                ));

                return $instructions;
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
    protected function getDropIndexByNameInstructions(string $tableName, string $indexName): AlterInstructions
    {
        $indexes = $this->getIndexes($tableName);
        $instructions = new AlterInstructions();

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                $instructions->addPostStep(sprintf(
                    'DROP INDEX %s ON %s',
                    $this->quoteColumnName($indexName),
                    $this->quoteTableName($tableName),
                ));

                return $instructions;
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
    public function hasPrimaryKey(string $tableName, $columns, ?string $constraint = null): bool
    {
        $primaryKey = $this->getPrimaryKey($tableName);
        if (!$primaryKey) {
            return false;
        }

        if ($constraint) {
            return $primaryKey['constraint'] === $constraint;
        }

        return $primaryKey['columns'] === (array)$columns;
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
            'columns' => [],
        ];
        foreach ($indexes as $row) {
            if ($row['type'] == TableSchema::CONSTRAINT_PRIMARY) {
                return $row;
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

        return $dialect->describeForeignKeys($tableName);
    }

    /**
     * @inheritDoc
     */
    protected function getAddForeignKeyInstructions(TableMetadata $table, ForeignKey $foreignKey): AlterInstructions
    {
        $instructions = new AlterInstructions();
        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s ADD %s',
            $this->quoteTableName($table->getName()),
            $this->getForeignKeySqlDefinition($foreignKey, $table->getName()),
        ));

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    protected function getDropForeignKeyInstructions(string $tableName, string $constraint): AlterInstructions
    {
        $instructions = new AlterInstructions();
        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s DROP CONSTRAINT %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($constraint),
        ));

        return $instructions;
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
     * @inheritDoc
     */
    public function createDatabase(string $name, array $options = []): void
    {
        $quotedName = $this->quoteSchemaName($name);
        if (isset($options['collation'])) {
            $this->execute(sprintf(
                'CREATE DATABASE %s COLLATE %s',
                $quotedName,
                $this->quoteSchemaName($options['collation']),
            ));
        } else {
            $this->execute(sprintf('CREATE DATABASE %s', $quotedName));
        }
        $this->execute(sprintf('USE %s', $quotedName));
    }

    /**
     * @inheritDoc
     */
    public function hasDatabase(string $name): bool
    {
        /** @var array<string, mixed> $result */
        $result = $this->query(
            'SELECT count(*) as [count] FROM master.dbo.sysdatabases WHERE [name] = ?',
            [$name],
        )->fetch('assoc');

        return $result['count'] > 0;
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase(string $name): void
    {
        $quotedName = $this->quoteSchemaName($name);
        $sql = sprintf(
            'USE master;
IF EXISTS(select * from sys.databases where name=%s)
ALTER DATABASE %s SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
DROP DATABASE %s;',
            $this->quoteString($name),
            $quotedName,
            $quotedName,
        );
        $this->execute($sql);
        $this->createdTables = [];
    }

    /**
     * Gets the SqlServer Index Definition for an Index object.
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
        if ($indexName == '') {
            $indexName = sprintf('%s_%s', $parts['table'], implode('_', $columnNames));
        }
        $order = $index->getOrder() ?? [];
        $columnNames = array_map(function ($columnName) use ($order) {
            $ret = '[' . $columnName . ']';
            if (isset($order[$columnName])) {
                $ret .= ' ' . $order[$columnName];
            }

            return $ret;
        }, $columnNames);

        $include = $index->getInclude();
        $includedColumns = $include ? sprintf(' INCLUDE ([%s])', implode('],[', $include)) : '';
        $where = (string)$index->getWhere();
        if ($where) {
            $where = ' WHERE ' . $where;
        }

        return sprintf(
            'CREATE %s INDEX %s ON %s (%s)%s%s;',
            ($index->getType() === Index::UNIQUE ? 'UNIQUE' : ''),
            $indexName,
            $this->quoteTableName($tableName),
            implode(',', $columnNames),
            $includedColumns,
            $where,
        );
    }

    /**
     * Gets the SqlServer Foreign Key Definition for an ForeignKey object.
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

        $def = ' CONSTRAINT ' . $this->quoteColumnName($constraintName);
        $def .= ' FOREIGN KEY (' . $columnList . ')';
        $def .= ' REFERENCES ' . $this->quoteTableName($foreignKey->getReferencedTable()) . ' (' . $refColumnList . ')';
        if ($foreignKey->getOnDelete()) {
            $def .= " ON DELETE {$foreignKey->getOnDelete()}";
        }
        if ($foreignKey->getOnUpdate()) {
            $def .= " ON UPDATE {$foreignKey->getOnUpdate()}";
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
        $baseName = $tableName . '_' . implode('_', $columns);
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
     * Creates the specified schema.
     *
     * @param string $schemaName Schema Name
     * @return void
     */
    public function createSchema(string $schemaName = 'public'): void
    {
        if ($this->hasSchema($schemaName) === false) {
            $sql = sprintf('CREATE SCHEMA %s', $this->quoteColumnName($schemaName));
            $this->execute($sql);
        }
    }

    /**
     * Checks to see if a schema exists.
     *
     * @param string $schemaName Schema Name
     * @return bool
     */
    public function hasSchema(string $schemaName): bool
    {
        $sql = 'SELECT count(*) AS [count] FROM sys.schemas WHERE name = ?';
        $result = $this->query($sql, [$schemaName])->fetch('assoc');
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
        $sql = sprintf('DROP SCHEMA IF EXISTS %s', $this->quoteSchemaName($schemaName));
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
        $sql = "SELECT name
                FROM sys.schemas
                WHERE name not in ('information_schema', 'sys', 'guest', 'dbo') AND name not like 'db_%'";
        $items = $this->fetchAll($sql);
        $schemaNames = [];
        foreach ($items as $item) {
            $schemaNames[] = $item['name'];
        }

        return $schemaNames;
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

        return empty($config['schema']) ? $this->schema : $config['schema'];
    }

    /**
     * @inheritDoc
     */
    public function getColumnTypes(): array
    {
        return array_merge(parent::getColumnTypes(), static::$specificColumnTypes);
    }

    /**
     * Records a migration being run.
     *
     * @param \Migrations\MigrationInterface $migration Migration
     * @param string $direction Direction
     * @param string $startTime Start Time
     * @param string $endTime End Time
     * @return \Migrations\Db\Adapter\AdapterInterface
     */
    public function migrated(MigrationInterface $migration, string $direction, string $startTime, string $endTime): AdapterInterface
    {
        $startTime = str_replace(' ', 'T', $startTime);
        $endTime = str_replace(' ', 'T', $endTime);

        return parent::migrated($migration, $direction, $startTime, $endTime);
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
        $sql = $this->generateInsertSql($table, $row, $mode, $updateColumns, $conflictColumns);

        $sql = $this->updateSQLForIdentityInsert($table->getName(), $sql);

        if ($this->isDryRunEnabled()) {
            $this->io->out($sql);
        } else {
            $vals = [];
            foreach ($row as $value) {
                $placeholder = '?';
                if ($value instanceof Literal) {
                    $placeholder = (string)$value;
                }
                if ($placeholder === '?') {
                    $vals[] = $value;
                }
            }
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
        $sql = $this->generateBulkInsertSql($table, $rows, $mode, $updateColumns, $conflictColumns);

        $sql = $this->updateSQLForIdentityInsert($table->getName(), $sql);

        if ($this->isDryRunEnabled()) {
            $this->io->out($sql);
        } else {
            $vals = [];
            foreach ($rows as $row) {
                foreach ($row as $v) {
                    $placeholder = '?';
                    if ($v instanceof Literal) {
                        $placeholder = (string)$v;
                    }
                    if ($placeholder == '?') {
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
            }
            $this->getConnection()->execute($sql, $vals);
        }
    }

    /**
     * @param string $tableName Table name
     * @param string $sql SQL statement
     * @return string
     */
    private function updateSQLForIdentityInsert(string $tableName, string $sql): string
    {
        $options = $this->getOptions();
        if (isset($options['identity_insert']) && $options['identity_insert'] == true) {
            $identityInsertStart = sprintf(
                'SET IDENTITY_INSERT %s ON',
                $this->quoteTableName($tableName),
            );
            $identityInsertEnd = sprintf(
                'SET IDENTITY_INSERT %s OFF',
                $this->quoteTableName($tableName),
            );
            $sql = $identityInsertStart . ';' . PHP_EOL . $sql . ';' . PHP_EOL . $identityInsertEnd;
        }

        return $sql;
    }

    /**
     * @inheritDoc
     *
     * Note: Check constraints are not supported for SQL Server adapter.
     * This method returns an empty array. Use raw SQL via execute() if you need
     * check constraints on SQL Server.
     */
    protected function getCheckConstraints(string $tableName): array
    {
        return [];
    }

    /**
     * @inheritDoc
     * @throws \BadMethodCallException Check constraints are not supported for SQL Server.
     */
    protected function getAddCheckConstraintInstructions(TableMetadata $table, CheckConstraint $checkConstraint): AlterInstructions
    {
        throw new BadMethodCallException(
            'Check constraints are not supported for the SQL Server adapter. ' .
            'Use $this->execute() with raw SQL to add check constraints.',
        );
    }

    /**
     * @inheritDoc
     * @throws \BadMethodCallException Check constraints are not supported for SQL Server.
     */
    protected function getDropCheckConstraintInstructions(string $tableName, string $constraintName): AlterInstructions
    {
        throw new BadMethodCallException(
            'Check constraints are not supported for the SQL Server adapter. ' .
            'Use $this->execute() with raw SQL to drop check constraints.',
        );
    }

    /**
     * @inheritDoc
     */
    protected function getInsertPrefix(?InsertMode $mode = null): string
    {
        if ($mode === InsertMode::IGNORE) {
            throw new BadMethodCallException('INSERT IGNORE is not supported for SQL Server');
        }

        return parent::getInsertPrefix($mode);
    }
}
