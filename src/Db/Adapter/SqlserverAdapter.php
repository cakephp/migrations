<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use BadMethodCallException;
use InvalidArgumentException;
use Migrations\Db\AlterInstructions;
use Migrations\Db\Literal;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\Table;
use Migrations\MigrationInterface;

/**
 * Migrations SqlServer Adapter.
 */
class SqlserverAdapter extends AbstractAdapter
{
    /**
     * @var string[]
     */
    protected static array $specificColumnTypes = [
        self::PHINX_TYPE_FILESTREAM,
        self::PHINX_TYPE_BINARYUUID,
        self::PHINX_TYPE_NATIVEUUID,
    ];

    /**
     * @var string
     */
    protected string $schema = 'dbo';

    /**
     * @var bool[]
     */
    protected array $signedColumnTypes = [
        self::PHINX_TYPE_INTEGER => true,
        self::PHINX_TYPE_BIG_INTEGER => true,
        self::PHINX_TYPE_FLOAT => true,
        self::PHINX_TYPE_DECIMAL => true,
    ];

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
        if ($this->hasCreatedTable($tableName)) {
            return true;
        }
        $dialect = $this->getSchemaDialect();

        $parts = $this->getSchemaName($tableName);
        [$query, $params] = $dialect->listTablesSql(['schema' => $parts['schema']]);

        $rows = $this->query($query, $params)->fetchAll();
        $tables = array_column($rows, 0);

        return in_array($parts['table'], $tables, true);
    }

    /**
     * @inheritDoc
     */
    public function createTable(Table $table, array $columns = [], array $indexes = []): void
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

        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteTableName($table->getName()) . ' (';
        $sqlBuffer = [];
        $columnsWithComments = [];
        foreach ($columns as $column) {
            $sqlBuffer[] = $this->quoteColumnName((string)$column->getName()) . ' ' . $this->getColumnSqlDefinition($column);

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
    protected function getChangePrimaryKeyInstructions(Table $table, $newColumns): AlterInstructions
    {
        $instructions = new AlterInstructions();

        // Drop the existing primary key
        $primaryKey = $this->getPrimaryKey($table->getName());
        if (!empty($primaryKey['constraint'])) {
            $sql = sprintf(
                'DROP CONSTRAINT %s',
                $this->quoteColumnName($primaryKey['constraint'])
            );
            $instructions->addAlter($sql);
        }

        // Add the primary key(s)
        if (!empty($newColumns)) {
            $sql = sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s PRIMARY KEY (',
                $this->quoteTableName($table->getName()),
                $this->quoteColumnName('PK_' . $table->getName())
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
    protected function getChangeCommentInstructions(Table $table, ?string $newComment): AlterInstructions
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
            (string)$column->getName()
        );
    }

    /**
     * @inheritDoc
     */
    protected function getRenameTableInstructions(string $tableName, string $newTableName): AlterInstructions
    {
        $this->updateCreatedTableName($tableName, $newTableName);
        $sql = sprintf(
            "EXEC sp_rename '%s', '%s'",
            $tableName,
            $newTableName
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
            $this->quoteTableName($tableName)
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
        // TODO we can't use cakephp/database for reflection here
        // as we'd be missing some attributes.
        $parts = $this->getSchemaName($tableName);
        $columns = [];
        $sql = "SELECT
            DISTINCT TABLE_SCHEMA AS [schema],
            TABLE_NAME as [table_name],
            COLUMN_NAME AS [name],
            DATA_TYPE AS [type],
            IS_NULLABLE AS [null], COLUMN_DEFAULT AS [default],
            CHARACTER_MAXIMUM_LENGTH AS [char_length],
            NUMERIC_PRECISION AS [precision],
            NUMERIC_SCALE AS [scale],
            ORDINAL_POSITION AS [ordinal_position],
            COLUMNPROPERTY(object_id(TABLE_NAME),
            COLUMN_NAME, 'IsIdentity') as [identity]
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ORDER BY ordinal_position";
        $rows = $this->query($sql, [$parts['schema'], $parts['table']])
            ->fetchAll('assoc');
        foreach ($rows as $columnInfo) {
            try {
                $type = $this->getPhinxType($columnInfo['type']);
            } catch (UnsupportedColumnTypeException $e) {
                $type = Literal::from($columnInfo['type']);
            }

            $column = new Column();
            $column->setName($columnInfo['name'])
                   ->setType($type)
                   ->setNull($columnInfo['null'] !== 'NO')
                   ->setDefault($this->parseDefault($columnInfo['default']))
                   ->setIdentity($columnInfo['identity'] === '1')
                   ->setComment($this->getColumnComment($columnInfo['table_name'], $columnInfo['name']));

            if (!empty($columnInfo['char_length'])) {
                $column->setLimit((int)$columnInfo['char_length']);
            }

            $columns[$columnInfo['name']] = $column;
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
    public function hasColumn(string $tableName, string $columnName): bool
    {
        $parts = $this->getSchemaName($tableName);
        $sql = "SELECT count(*) as [count]
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        /** @var array<string, mixed> $result */
        $result = $this->query($sql, [$parts['schema'], $parts['table'], $columnName])->fetch('assoc');

        return $result['count'] > 0;
    }

    /**
     * @inheritDoc
     */
    protected function getAddColumnInstructions(Table $table, Column $column): AlterInstructions
    {
        $alter = sprintf(
            'ALTER TABLE %s ADD %s %s',
            $table->getName(),
            $this->quoteColumnName((string)$column->getName()),
            $this->getColumnSqlDefinition($column)
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
        $sql = <<<SQL
IF (OBJECT_ID('$oldConstraintName', 'D') IS NOT NULL)
BEGIN
     EXECUTE sp_rename N'%s', N'%s', N'OBJECT'
END
SQL;
        $instructions->addPostStep(sprintf(
            $sql,
            $oldConstraintName,
            $newConstraintName
        ));

        $instructions->addPostStep(sprintf(
            "EXECUTE sp_rename N'%s.%s', N'%s', 'COLUMN' ",
            $tableName,
            $columnName,
            $newColumnName
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
            $default = ltrim($this->getDefaultValueDefinition($default));
        }

        if (empty($default)) {
            return $instructions;
        }

        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s %s FOR %s',
            $this->quoteTableName($tableName),
            $constraintName,
            $default,
            $this->quoteColumnName((string)$newColumn->getName())
        ));

        return $instructions;
    }

    /**
     * @inheritDoc
     */
    protected function getChangeColumnInstructions(string $tableName, string $columnName, Column $newColumn): AlterInstructions
    {
        $columns = $this->getColumns($tableName);
        $changeDefault =
            $newColumn->getDefault() !== $columns[$columnName]->getDefault() ||
            $newColumn->getType() !== $columns[$columnName]->getType();

        $instructions = new AlterInstructions();

        if ($columnName !== $newColumn->getName()) {
            $instructions->merge(
                $this->getRenameColumnInstructions($tableName, $columnName, (string)$newColumn->getName())
            );
        }

        if ($changeDefault) {
            $instructions->merge($this->getDropDefaultConstraint($tableName, (string)$newColumn->getName()));
        }

        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s ALTER COLUMN %s %s',
            $this->quoteTableName($tableName),
            $this->quoteColumnName((string)$newColumn->getName()),
            $this->getColumnSqlDefinition($newColumn, false)
        ));
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
            $this->quoteColumnName($columnName)
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
        $parts = $this->getSchemaName($tableName);
        $dialect = $this->getSchemaDialect();

        [$query, $params] = $dialect->describeIndexSql($parts['table'], ['schema' => $parts['schema']]);
        $rows = $this->query($query, $params)->fetchAll('assoc');
        $indexes = [];
        foreach ($rows as $row) {
            $name = $row['index_name'];
            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'columns' => [],
                    'isPrimary' => false,
                ];
            }
            $indexes[$name]['columns'][] = $row['column_name'];
            $indexes[$name]['isPrimary'] = $row['is_primary_key'];
        }

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
            $a = array_diff($columns, $index['columns']);

            if (empty($a)) {
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

        foreach ($indexes as $name => $index) {
            if ($name === $indexName) {
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

        foreach ($indexes as $indexName => $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                $instructions->addPostStep(sprintf(
                    'DROP INDEX %s ON %s',
                    $this->quoteColumnName($indexName),
                    $this->quoteTableName($tableName)
                ));

                return $instructions;
            }
        }

        throw new InvalidArgumentException(sprintf(
            "The specified index on columns '%s' does not exist",
            implode(',', $columns)
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

        foreach ($indexes as $name => $index) {
            if ($name === $indexName) {
                $instructions->addPostStep(sprintf(
                    'DROP INDEX %s ON %s',
                    $this->quoteColumnName($indexName),
                    $this->quoteTableName($tableName)
                ));

                return $instructions;
            }
        }

        throw new InvalidArgumentException(sprintf(
            "The specified index name '%s' does not exist",
            $indexName
        ));
    }

    /**
     * @inheritDoc
     */
    public function hasPrimaryKey(string $tableName, $columns, ?string $constraint = null): bool
    {
        $primaryKey = $this->getPrimaryKey($tableName);

        if (empty($primaryKey)) {
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
        foreach ($indexes as $name => $row) {
            if ($row['isPrimary']) {
                $primaryKey['constraint'] = $name;
                $primaryKey['columns'] = $row['columns'];
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
        if ($constraint) {
            if (isset($foreignKeys[$constraint])) {
                return !empty($foreignKeys[$constraint]);
            }

            return false;
        }

        if (is_string($columns)) {
            $columns = [$columns];
        }

        foreach ($foreignKeys as $key) {
            if ($key['columns'] === $columns) {
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
        $parts = $this->getSchemaName($tableName);
        $dialect = $this->getSchemaDialect();
        $foreignKeys = [];

        [$query, $params] = $dialect->describeForeignKeySql($parts['table'], ['schema' => $parts['schema']]);
        $rows = $this->query($query, $params)->fetchAll('assoc');

        foreach ($rows as $row) {
            $name = $row['foreign_key_name'];
            if (!isset($foreignKeys[$name])) {
                $foreignKeys[$name] = [
                    'table' => '',
                    'columns' => [],
                    'referenced_table' => '',
                    'referenced_columns' => [],
                ];
            }
            $foreignKeys[$name]['table'] = $parts['table'];
            $foreignKeys[$name]['columns'][] = $row['column'];
            $foreignKeys[$name]['referenced_table'] = $row['reference_table'];
            $foreignKeys[$name]['referenced_columns'][] = $row['reference_column'];
        }
        foreach ($foreignKeys as $name => $key) {
            $foreignKeys[$name]['columns'] = array_values(array_unique($key['columns']));
            $foreignKeys[$name]['referenced_columns'] = array_values(array_unique($key['referenced_columns']));
        }

        return $foreignKeys;
    }

    /**
     * @inheritDoc
     */
    protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey): AlterInstructions
    {
        $instructions = new AlterInstructions();
        $instructions->addPostStep(sprintf(
            'ALTER TABLE %s ADD %s',
            $this->quoteTableName($table->getName()),
            $this->getForeignKeySqlDefinition($foreignKey, $table->getName())
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
            $this->quoteColumnName($constraint)
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
        foreach ($foreignKeys as $name => $key) {
            if ($key['columns'] === $columns) {
                $matches[] = $name;
            }
        }

        if (empty($matches)) {
            throw new InvalidArgumentException(sprintf(
                'No foreign key on column(s) `%s` exists',
                implode(', ', $columns)
            ));
        }

        foreach ($matches as $name) {
            $instructions->merge(
                $this->getDropForeignKeyInstructions($tableName, $name)
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
            case static::PHINX_TYPE_DECIMAL:
            case static::PHINX_TYPE_DATETIME:
            case static::PHINX_TYPE_TIME:
            case static::PHINX_TYPE_DATE:
                return ['name' => $type];
            case static::PHINX_TYPE_STRING:
                return ['name' => 'nvarchar', 'limit' => 255];
            case static::PHINX_TYPE_CHAR:
                return ['name' => 'nchar', 'limit' => 255];
            case static::PHINX_TYPE_TEXT:
                return ['name' => 'ntext'];
            case static::PHINX_TYPE_INTEGER:
                return ['name' => 'int'];
            case static::PHINX_TYPE_TINY_INTEGER:
                return ['name' => 'tinyint'];
            case static::PHINX_TYPE_SMALL_INTEGER:
                return ['name' => 'smallint'];
            case static::PHINX_TYPE_BIG_INTEGER:
                return ['name' => 'bigint'];
            case static::PHINX_TYPE_TIMESTAMP:
                return ['name' => 'datetime'];
            case static::PHINX_TYPE_BLOB:
            case static::PHINX_TYPE_BINARY:
                return ['name' => 'varbinary'];
            case static::PHINX_TYPE_BOOLEAN:
                return ['name' => 'bit'];
            case static::PHINX_TYPE_BINARYUUID:
            case static::PHINX_TYPE_UUID:
            case static::PHINX_TYPE_NATIVEUUID:
                return ['name' => 'uniqueidentifier'];
            case static::PHINX_TYPE_FILESTREAM:
                return ['name' => 'varbinary', 'limit' => 'max'];
            // Geospatial database types
            case static::PHINX_TYPE_GEOGRAPHY:
            case static::PHINX_TYPE_POINT:
            case static::PHINX_TYPE_LINESTRING:
            case static::PHINX_TYPE_POLYGON:
                // SQL Server stores all spatial data using a single data type.
                // Specific types (point, polygon, etc) are set at insert time.
                return ['name' => 'geography'];
            // Geometry specific type
            case static::PHINX_TYPE_GEOMETRY:
                return ['name' => 'geometry'];
            default:
                throw new UnsupportedColumnTypeException('Column type "' . $type . '" is not supported by SqlServer.');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @internal param string $sqlType SQL type
     * @param string $sqlType SQL Type definition
     * @throws \Migrations\Db\Adapter\UnsupportedColumnTypeException
     * @return string Phinx type
     */
    public function getPhinxType(string $sqlType): string
    {
        switch ($sqlType) {
            case 'nvarchar':
            case 'varchar':
                return static::PHINX_TYPE_STRING;
            case 'char':
            case 'nchar':
                return static::PHINX_TYPE_CHAR;
            case 'text':
            case 'ntext':
                return static::PHINX_TYPE_TEXT;
            case 'int':
            case 'integer':
                return static::PHINX_TYPE_INTEGER;
            case 'decimal':
            case 'numeric':
            case 'money':
                return static::PHINX_TYPE_DECIMAL;
            case 'tinyint':
                return static::PHINX_TYPE_TINY_INTEGER;
            case 'smallint':
                return static::PHINX_TYPE_SMALL_INTEGER;
            case 'bigint':
                return static::PHINX_TYPE_BIG_INTEGER;
            case 'real':
            case 'float':
                return static::PHINX_TYPE_FLOAT;
            case 'binary':
            case 'image':
            case 'varbinary':
                return static::PHINX_TYPE_BINARY;
            case 'time':
                return static::PHINX_TYPE_TIME;
            case 'date':
                return static::PHINX_TYPE_DATE;
            case 'datetime':
            case 'timestamp':
                return static::PHINX_TYPE_DATETIME;
            case 'bit':
                return static::PHINX_TYPE_BOOLEAN;
            case 'uniqueidentifier':
                return static::PHINX_TYPE_UUID;
            case 'filestream':
                return static::PHINX_TYPE_FILESTREAM;
            default:
                throw new UnsupportedColumnTypeException('Column type "' . $sqlType . '" is not supported by SqlServer.');
        }
    }

    /**
     * @inheritDoc
     */
    public function createDatabase(string $name, array $options = []): void
    {
        if (isset($options['collation'])) {
            $this->execute(sprintf('CREATE DATABASE [%s] COLLATE [%s]', $name, $options['collation']));
        } else {
            $this->execute(sprintf('CREATE DATABASE [%s]', $name));
        }
        $this->execute(sprintf('USE [%s]', $name));
    }

    /**
     * @inheritDoc
     */
    public function hasDatabase(string $name): bool
    {
        /** @var array<string, mixed> $result */
        $result = $this->query(
            'SELECT count(*) as [count] FROM master.dbo.sysdatabases WHERE [name] = ?',
            [$name]
        )->fetch('assoc');

        return $result['count'] > 0;
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase(string $name): void
    {
        $sql = <<<SQL
USE master;
IF EXISTS(select * from sys.databases where name=N'$name')
ALTER DATABASE [$name] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
DROP DATABASE [$name];
SQL;
        $this->execute($sql);
        $this->createdTables = [];
    }

    /**
     * Gets the SqlServer Column Definition for a Column object.
     *
     * @param \Migrations\Db\Table\Column $column Column
     * @param bool $create Create column flag
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column, bool $create = true): string
    {
        $buffer = [];
        if ($column->getType() instanceof Literal) {
            $buffer[] = (string)$column->getType();
        } else {
            $sqlType = $this->getSqlType($column->getType());
            $buffer[] = strtoupper($sqlType['name']);
            // integers cant have limits in SQlServer
            $noLimits = [
                'bigint',
                'int',
                'tinyint',
                'smallint',
            ];
            if ($sqlType['name'] === static::PHINX_TYPE_DECIMAL && $column->getPrecision() && $column->getScale()) {
                $buffer[] = sprintf(
                    '(%s, %s)',
                    $column->getPrecision() ?: $sqlType['precision'],
                    $column->getScale() ?: $sqlType['scale']
                );
            } elseif (!in_array($sqlType['name'], $noLimits) && ($column->getLimit() || isset($sqlType['limit']))) {
                $buffer[] = sprintf('(%s)', $column->getLimit() ?: $sqlType['limit']);
            }
        }

        $properties = $column->getProperties();
        $buffer[] = $column->getType() === 'filestream' ? 'FILESTREAM' : '';
        $buffer[] = isset($properties['rowguidcol']) ? 'ROWGUIDCOL' : '';

        $buffer[] = $column->isNull() ? 'NULL' : 'NOT NULL';

        if ($create === true) {
            if ($column->getDefault() === null && $column->isNull()) {
                $buffer[] = ' DEFAULT NULL';
            } else {
                $buffer[] = $this->getDefaultValueDefinition($column->getDefault());
            }
        }

        if ($column->isIdentity()) {
            $seed = $column->getSeed() ?: 1;
            $increment = $column->getIncrement() ?: 1;
            $buffer[] = sprintf('IDENTITY(%d,%d)', $seed, $increment);
        }

        return implode(' ', $buffer);
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
        if (!is_string($indexName)) {
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
            $where
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
        $constraintName = $foreignKey->getConstraint() ?: $tableName . '_' . implode('_', $foreignKey->getColumns());
        $def = ' CONSTRAINT ' . $this->quoteColumnName($constraintName);
        $def .= ' FOREIGN KEY ("' . implode('", "', $foreignKey->getColumns()) . '")';
        $def .= " REFERENCES {$this->quoteTableName($foreignKey->getReferencedTable()->getName())} (\"" . implode('", "', $foreignKey->getReferencedColumns()) . '")';
        if ($foreignKey->getOnDelete()) {
            $def .= " ON DELETE {$foreignKey->getOnDelete()}";
        }
        if ($foreignKey->getOnUpdate()) {
            $def .= " ON UPDATE {$foreignKey->getOnUpdate()}";
        }

        return $def;
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
     * @return \Phinx\Db\Adapter\AdapterInterface
     */
    public function migrated(MigrationInterface $migration, string $direction, string $startTime, string $endTime): AdapterInterface
    {
        $startTime = str_replace(' ', 'T', $startTime);
        $endTime = str_replace(' ', 'T', $endTime);

        return parent::migrated($migration, $direction, $startTime, $endTime);
    }
}
