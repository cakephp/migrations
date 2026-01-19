<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use InvalidArgumentException;
use Migrations\Db\Action\AddColumn;
use Migrations\Db\Action\AddForeignKey;
use Migrations\Db\Action\AddIndex;
use Migrations\Db\Action\AddPartition;
use Migrations\Db\Action\ChangeColumn;
use Migrations\Db\Action\ChangeComment;
use Migrations\Db\Action\ChangePrimaryKey;
use Migrations\Db\Action\CreateTable;
use Migrations\Db\Action\DropForeignKey;
use Migrations\Db\Action\DropIndex;
use Migrations\Db\Action\DropPartition;
use Migrations\Db\Action\DropTable;
use Migrations\Db\Action\RemoveColumn;
use Migrations\Db\Action\RenameColumn;
use Migrations\Db\Action\RenameTable;
use Migrations\Db\Action\SetPartitioning;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Adapter\MysqlAdapter;
use Migrations\Db\Plan\Intent;
use Migrations\Db\Plan\Plan;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\Partition;
use Migrations\Db\Table\PartitionDefinition;
use Migrations\Db\Table\TableMetadata;
use RuntimeException;

/**
 * Migration Table
 *
 * Table instances allow you to define schema changes
 * to be made on a table. You can manipulate columns,
 * indexes and foreign keys. You can also manipulate table
 * data with row operations.
 *
 * This object is based loosely on: https://api.rubyonrails.org/classes/ActiveRecord/ConnectionAdapters/Table.html.
 */
class Table
{
    /**
     * @var \Migrations\Db\Table\TableMetadata
     */
    protected TableMetadata $table;

    /**
     * @var \Migrations\Db\Adapter\AdapterInterface|null
     */
    protected ?AdapterInterface $adapter = null;

    /**
     * @var \Migrations\Db\Plan\Intent
     */
    protected Intent $actions;

    /**
     * @var array
     */
    protected array $data = [];

    /**
     * Insert mode for data operations
     *
     * @var \Migrations\Db\InsertMode|null
     */
    protected ?InsertMode $insertMode = null;

    /**
     * Columns to update on upsert conflict
     *
     * @var array<string>|null
     */
    protected ?array $upsertUpdateColumns = null;

    /**
     * Columns that define uniqueness for upsert conflict detection
     *
     * @var array<string>|null
     */
    protected ?array $upsertConflictColumns = null;

    /**
     * Primary key for this table.
     * Can either be a string or an array in case of composite
     * primary key.
     *
     * @var string|string[]
     */
    protected string|array $primaryKey;

    /**
     * @param string $name Table Name
     * @param array<string, mixed> $options Options
     * @param \Migrations\Db\Adapter\AdapterInterface|null $adapter Database Adapter
     */
    public function __construct(string $name, array $options = [], ?AdapterInterface $adapter = null)
    {
        $this->table = new TableMetadata($name, $options);
        $this->actions = new Intent();

        if ($adapter !== null) {
            $this->setAdapter($adapter);
        }
    }

    /**
     * Gets the table name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->table->getName();
    }

    /**
     * Gets the table options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->table->getOptions();
    }

    /**
     * Gets the table name and options as an object
     *
     * @return \Migrations\Db\Table\TableMetadata
     */
    public function getTable(): TableMetadata
    {
        return $this->table;
    }

    /**
     * Sets the database adapter.
     *
     * @param \Migrations\Db\Adapter\AdapterInterface $adapter Database Adapter
     * @return $this
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Gets the database adapter.
     *
     * @throws \RuntimeException
     * @return \Migrations\Db\Adapter\AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        if (!$this->adapter) {
            throw new RuntimeException('There is no database adapter set yet, cannot proceed');
        }

        return $this->adapter;
    }

    /**
     * Does the table have pending actions?
     *
     * @return bool
     */
    public function hasPendingActions(): bool
    {
        return count($this->actions->getActions()) > 0 || count($this->data) > 0;
    }

    /**
     * Does the table exist?
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->getAdapter()->hasTable($this->getName());
    }

    /**
     * Drops the database table.
     *
     * @return $this
     */
    public function drop()
    {
        $this->actions->addAction(new DropTable($this->table));

        return $this;
    }

    /**
     * Renames the database table.
     *
     * @param string $newTableName New Table Name
     * @return $this
     */
    public function rename(string $newTableName)
    {
        $this->actions->addAction(new RenameTable($this->table, $newTableName));

        return $this;
    }

    /**
     * Changes the primary key of the database table.
     *
     * @param string|string[]|null $columns Column name(s) to belong to the primary key, or null to drop the key
     * @return $this
     */
    public function changePrimaryKey(string|array|null $columns)
    {
        $this->actions->addAction(new ChangePrimaryKey($this->table, $columns));

        return $this;
    }

    /**
     * Checks to see if a primary key exists.
     *
     * @param string|string[] $columns Column(s)
     * @param string|null $constraint Constraint names
     * @return bool
     */
    public function hasPrimaryKey(string|array $columns, ?string $constraint = null): bool
    {
        return $this->getAdapter()->hasPrimaryKey($this->getName(), $columns, $constraint);
    }

    /**
     * Changes the comment of the database table.
     *
     * @param string|null $comment New comment string, or null to drop the comment
     * @return $this
     */
    public function changeComment(?string $comment)
    {
        $this->actions->addAction(new ChangeComment($this->table, $comment));

        return $this;
    }

    /**
     * Gets an array of the table columns.
     *
     * @return \Migrations\Db\Table\Column[]
     */
    public function getColumns(): array
    {
        return $this->getAdapter()->getColumns($this->getName());
    }

    /**
     * Gets a table column if it exists.
     *
     * @param string $name Column name
     * @return \Migrations\Db\Table\Column|null
     */
    public function getColumn(string $name): ?Column
    {
        $columns = array_filter(
            $this->getColumns(),
            function ($column) use ($name) {
                return $column->getName() === $name;
            },
        );

        return array_pop($columns);
    }

    /**
     * Sets an array of data to be inserted.
     *
     * @param array<string, mixed> $data Data
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Gets the data waiting to be inserted.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Resets all of the pending data to be inserted
     *
     * @return void
     */
    public function resetData(): void
    {
        $this->setData([]);
    }

    /**
     * Resets all of the pending table changes.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->actions = new Intent();
        $this->resetData();
    }

    /**
     * Add a primary key to a database table.
     *
     * @param string|string[] $columns Table Column(s)
     * @return $this
     */
    public function addPrimaryKey(string|array $columns)
    {
        $this->primaryKey = $columns;

        return $this;
    }

    /**
     * Add a table column.
     *
     * Type can be: string, text, integer, float, decimal, datetime, timestamp,
     * time, date, binary, boolean.
     *
     * Valid options can be: limit, default, null, precision or scale.
     *
     * @param string|\Migrations\Db\Table\Column $columnName Column Name
     * @param string|null $type Column Type
     * @param array<string, mixed> $options Column Options
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function addColumn(string|Column $columnName, ?string $type = null, array $options = [])
    {
        assert($columnName instanceof Column || $type !== null);
        if ($columnName instanceof Column) {
            $action = new AddColumn($this->table, $columnName);
        } else {
            $action = new AddColumn($this->table, $this->getAdapter()->getColumnForType($columnName, $type, $options));
        }

        // Delegate to Adapters to check column type
        if (!$this->getAdapter()->isValidColumnType($action->getColumn())) {
            throw new InvalidArgumentException(sprintf(
                'An invalid column type "%s" was specified for column "%s".',
                (string)$action->getColumn()->getType(),
                (string)$action->getColumn()->getName(),
            ));
        }

        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Remove a table column.
     *
     * @param string $columnName Column Name
     * @return $this
     */
    public function removeColumn(string $columnName)
    {
        $action = RemoveColumn::build($this->table, $columnName);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Rename a table column.
     *
     * @param string $oldName Old Column Name
     * @param string $newName New Column Name
     * @return $this
     */
    public function renameColumn(string $oldName, string $newName)
    {
        $action = RenameColumn::build($this->table, $oldName, $newName);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Update a table column, preserving unspecified attributes.
     *
     * This is the recommended method for modifying columns as it automatically
     * preserves existing column attributes (default, null, limit, etc.) unless
     * explicitly overridden.
     *
     * @param string $columnName Column Name
     * @param string|\Migrations\Db\Table\Column|null $newColumnType New Column Type (pass null to preserve existing type)
     * @param array<string, mixed> $options Options
     * @return $this
     */
    public function updateColumn(string $columnName, string|Column|null $newColumnType, array $options = [])
    {
        if (!($newColumnType instanceof Column)) {
            $options['preserveUnspecified'] = true;
        }

        return $this->changeColumn($columnName, $newColumnType, $options);
    }

    /**
     * Change a table column type.
     *
     * Note: This method replaces the column definition. Consider using updateColumn()
     * instead, which preserves unspecified attributes by default.
     *
     * @param string $columnName Column Name
     * @param string|\Migrations\Db\Table\Column|null $newColumnType New Column Type (pass null to preserve existing type)
     * @param array<string, mixed> $options Options
     * @return $this
     */
    public function changeColumn(string $columnName, string|Column|null $newColumnType, array $options = [])
    {
        if ($newColumnType instanceof Column) {
            if ($options) {
                throw new InvalidArgumentException(
                    'Cannot specify options array when passing a Column object. ' .
                    'Set all properties directly on the Column object instead.',
                );
            }
            $action = new ChangeColumn($this->table, $columnName, $newColumnType);
        } else {
            // Check if we should preserve existing column attributes
            $preserveUnspecified = $options['preserveUnspecified'] ?? false; // Default to false for BC
            unset($options['preserveUnspecified']);

            // If type is null, preserve the existing type
            if ($newColumnType === null) {
                if (!$this->hasColumn($columnName)) {
                    throw new RuntimeException(
                        "Cannot preserve column type for '$columnName' - column does not exist in table '{$this->getName()}'",
                    );
                }
                $existingColumn = $this->getColumn($columnName);
                if ($existingColumn === null) {
                    throw new RuntimeException(
                        "Cannot retrieve column definition for '$columnName' in table '{$this->getName()}'",
                    );
                }
                $newColumnType = $existingColumn->getType();
            }

            if ($preserveUnspecified && $this->hasColumn($columnName)) {
                // Get existing column definition
                $existingColumn = $this->getColumn($columnName);
                if ($existingColumn !== null) {
                    // Merge existing attributes with new ones
                    $options = $this->mergeColumnOptions($existingColumn, $newColumnType, $options);
                }
            }

            $action = ChangeColumn::build($this->table, $columnName, $newColumnType, $options);
        }
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Checks to see if a column exists.
     *
     * @param string $columnName Column Name
     * @return bool
     */
    public function hasColumn(string $columnName): bool
    {
        return $this->getAdapter()->hasColumn($this->getName(), $columnName);
    }

    /**
     * Add an index to a database table.
     *
     * In $options you can specify unique = true/false, and name (index name).
     *
     * @param string|array|\Migrations\Db\Table\Index $columns Table Column(s)
     * @param array<string, mixed> $options Index Options
     * @return $this
     */
    public function addIndex(string|array|Index $columns, array $options = [])
    {
        $action = AddIndex::build($this->table, $columns, $options);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Removes the given index from a table.
     *
     * @param string|string[] $columns Columns
     * @return $this
     */
    public function removeIndex(string|array $columns)
    {
        $action = DropIndex::build($this->table, is_string($columns) ? [$columns] : $columns);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Removes the given index identified by its name from a table.
     *
     * @param string $name Index name
     * @return $this
     */
    public function removeIndexByName(string $name)
    {
        $action = DropIndex::buildFromName($this->table, $name);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Checks to see if an index exists.
     *
     * @param string|string[] $columns Columns
     * @return bool
     */
    public function hasIndex(string|array $columns): bool
    {
        return $this->getAdapter()->hasIndex($this->getName(), $columns);
    }

    /**
     * Checks to see if an index specified by name exists.
     *
     * @param string $indexName Index name
     * @return bool
     */
    public function hasIndexByName(string $indexName): bool
    {
        return $this->getAdapter()->hasIndexByName($this->getName(), $indexName);
    }

    /**
     * Add a foreign key to a database table.
     *
     * In $options you can specify on_delete|on_delete = cascade|no_action ..,
     * on_update, constraint = constraint name.
     *
     * @param string|string[]|\Migrations\Db\Table\ForeignKey $columns Columns
     * @param string|\Migrations\Db\Table\TableMetadata $referencedTable Referenced Table
     * @param string|string[] $referencedColumns Referenced Columns
     * @param array<string, mixed> $options Options
     * @return $this
     */
    public function addForeignKey(string|array|ForeignKey $columns, string|TableMetadata|null $referencedTable = null, string|array $referencedColumns = ['id'], array $options = [])
    {
        if ($columns instanceof ForeignKey) {
            $action = new AddForeignKey($this->table, $columns);
        } else {
            if (!$referencedTable) {
                throw new InvalidArgumentException('Referenced table is required');
            }
            $action = AddForeignKey::build($this->table, $columns, $referencedTable, $referencedColumns, $options);
        }
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Removes the given foreign key from the table.
     *
     * @param string|string[] $columns Column(s)
     * @param string|null $constraint Constraint names
     * @return $this
     */
    public function dropForeignKey(string|array $columns, ?string $constraint = null)
    {
        $action = DropForeignKey::build($this->table, $columns, $constraint);
        $this->actions->addAction($action);

        return $this;
    }

    /**
     * Checks to see if a foreign key exists.
     *
     * @param string|string[] $columns Column(s)
     * @param string|null $constraint Constraint names
     * @return bool
     */
    public function hasForeignKey(string|array $columns, ?string $constraint = null): bool
    {
        return $this->getAdapter()->hasForeignKey($this->getName(), $columns, $constraint);
    }

    /**
     * Add partitioning to the table.
     *
     * @param string $type Partition type (RANGE, LIST, HASH, KEY)
     * @param string|string[]|\Migrations\Db\Literal $columns Column(s) or expression to partition by
     * @param array<string, mixed> $options Partition options (count for HASH/KEY)
     * @return $this
     */
    public function partitionBy(string $type, string|array|Literal $columns, array $options = [])
    {
        $partition = new Partition($type, $columns, [], $options['count'] ?? null, $options);
        $this->table->setPartition($partition);

        return $this;
    }

    /**
     * Add a partition definition (for RANGE/LIST types).
     *
     * @param string $name Partition name
     * @param mixed $value Boundary value (use 'MAXVALUE' for RANGE upper bound)
     * @param array<string, mixed> $options Additional options (tablespace, table for PG)
     * @return $this
     */
    public function addPartition(string $name, mixed $value = null, array $options = [])
    {
        $partition = $this->table->getPartition();
        if ($partition === null) {
            throw new RuntimeException('Must call partitionBy() before addPartition()');
        }

        $definition = new PartitionDefinition(
            $name,
            $value,
            $options['tablespace'] ?? null,
            $options['table'] ?? null,
            $options['comment'] ?? null,
        );
        $partition->addDefinition($definition);

        return $this;
    }

    /**
     * Remove a partition from an existing table.
     *
     * @param string $name Partition name
     * @return $this
     */
    public function dropPartition(string $name)
    {
        $this->actions->addAction(new DropPartition($this->table, $name));

        return $this;
    }

    /**
     * Add a partition to an existing partitioned table.
     *
     * @param string $name Partition name
     * @param mixed $value Boundary value
     * @param array<string, mixed> $options Additional options
     * @return $this
     */
    public function addPartitionToExisting(string $name, mixed $value, array $options = [])
    {
        $definition = new PartitionDefinition(
            $name,
            $value,
            $options['tablespace'] ?? null,
            $options['table'] ?? null,
            $options['comment'] ?? null,
        );
        $this->actions->addAction(new AddPartition($this->table, $definition));

        return $this;
    }

    /**
     * Add timestamp columns created_at and updated_at to the table.
     *
     * @param string|false|null $createdAt Alternate name for the created_at column
     * @param string|false|null $updatedAt Alternate name for the updated_at column
     * @param bool $withTimezone Whether to set the timezone option on the added columns
     * @return $this
     */
    public function addTimestamps(string|false|null $createdAt = 'created', string|false|null $updatedAt = 'updated', bool $withTimezone = false)
    {
        $createdAt = $createdAt ?? 'created';
        $updatedAt = $updatedAt ?? 'updated';

        if (!$createdAt && !$updatedAt) {
            throw new RuntimeException('Cannot set both created_at and updated_at columns to false');
        }
        $timestampConfig = (bool)Configure::read('Migrations.add_timestamps_use_datetime');
        $timestampType = 'timestamp';
        if ($timestampConfig === true) {
            $timestampType = 'datetime';
        }

        if ($createdAt) {
            $this->addColumn($createdAt, $timestampType, [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => '',
                'timezone' => $withTimezone,
            ]);
        }
        if ($updatedAt) {
            $this->addColumn($updatedAt, $timestampType, [
                'null' => true,
                'default' => null,
                'update' => 'CURRENT_TIMESTAMP',
                'timezone' => $withTimezone,
            ]);
        }

        return $this;
    }

    /**
     * Alias that always sets $withTimezone to true
     *
     * @see addTimestamps
     * @param string|false|null $createdAt Alternate name for the created_at column
     * @param string|false|null $updatedAt Alternate name for the updated_at column
     * @return $this
     */
    public function addTimestampsWithTimezone(string|false|null $createdAt = null, string|false|null $updatedAt = null)
    {
        $this->addTimestamps($createdAt, $updatedAt, true);

        return $this;
    }

    /**
     * Insert data into the table.
     *
     * @param array $data array of data in the form:
     *              array(
     *                  array("col1" => "value1", "col2" => "anotherValue1"),
     *                  array("col2" => "value2", "col2" => "anotherValue2"),
     *              )
     *              or array("col1" => "value1", "col2" => "anotherValue1")
     * @return $this
     */
    public function insert(array $data)
    {
        // handle array of array situations
        $keys = array_keys($data);
        $firstKey = array_shift($keys);
        if ($firstKey !== null && is_array($data[$firstKey])) {
            foreach ($data as $row) {
                $this->data[] = $row;
            }

            return $this;
        }

        if (count($data) > 0) {
            $this->data[] = $data;
        }

        return $this;
    }

    /**
     * Insert data into the table, skipping rows that would cause duplicate key conflicts.
     *
     * This method is idempotent and safe to run multiple times.
     *
     * @param array $data array of data in the same format as insert()
     * @return $this
     */
    public function insertOrSkip(array $data)
    {
        $this->insertMode = InsertMode::IGNORE;

        return $this->insert($data);
    }

    /**
     * Insert data into the table, updating specified columns on duplicate key conflicts.
     *
     * This method performs an "upsert" operation - inserting new rows and updating
     * existing rows that conflict on the specified unique columns.
     *
     * ### Database-specific behavior:
     *
     * - **MySQL**: Uses `ON DUPLICATE KEY UPDATE`. The `$conflictColumns` parameter is
     *   ignored because MySQL automatically applies the update to all unique constraint
     *   violations. Passing `$conflictColumns` will trigger a warning.
     *
     * - **PostgreSQL/SQLite**: Uses `ON CONFLICT (...) DO UPDATE SET`. The `$conflictColumns`
     *   parameter is required and must specify the columns that have a unique constraint.
     *   A RuntimeException will be thrown if this parameter is empty.
     *
     * - **SQL Server**: Not currently supported. Use separate insert/update logic.
     *
     * ### Example:
     * ```php
     * // Works on all supported databases
     * $table->insertOrUpdate([
     *     ['code' => 'USD', 'rate' => 1.0000],
     *     ['code' => 'EUR', 'rate' => 0.9234],
     * ], ['rate'], ['code']);
     * ```
     *
     * @param array $data array of data in the same format as insert()
     * @param array<string> $updateColumns Columns to update when a conflict occurs
     * @param array<string> $conflictColumns Columns that define uniqueness. Required for PostgreSQL/SQLite,
     *   ignored by MySQL (triggers warning if provided).
     * @return $this
     * @throws \RuntimeException When using PostgreSQL or SQLite without specifying conflictColumns
     */
    public function insertOrUpdate(array $data, array $updateColumns, array $conflictColumns)
    {
        $this->insertMode = InsertMode::UPSERT;
        $this->upsertUpdateColumns = $updateColumns;
        $this->upsertConflictColumns = $conflictColumns;

        return $this->insert($data);
    }

    /**
     * Creates a table from the object instance.
     *
     * @return void
     */
    public function create(): void
    {
        $options = $this->getTable()->getOptions();
        if ((!isset($options['id']) || $options['id'] === false) && !empty($this->primaryKey)) {
            $options['primary_key'] = (array)$this->primaryKey;
            $this->filterPrimaryKey($options);
        }

        $adapter = $this->getAdapter();
        if ($adapter instanceof MysqlAdapter && empty($options['collation'])) {
            $collation = $adapter->getDefaultCollation();
            if ($collation) {
                $options['collation'] = $collation;
            }
        }

        $this->getTable()->setOptions($options);

        $this->executeActions(false);
        $this->saveData();
        $this->reset(); // reset pending changes
    }

    /**
     * This method is called in case a primary key was defined using the addPrimaryKey() method.
     * It currently does something only if using SQLite.
     * If a column is an auto-increment key in SQLite, it has to be a primary key and it has to defined
     * when defining the column. Migrations takes care of that so we have to make sure columns defined as autoincrement
     * were not added with the addPrimaryKey method, otherwise, SQL queries will be wrong.
     *
     * @return void
     */
    protected function filterPrimaryKey(array $options): void
    {
        if ($this->getAdapter()->getAdapterType() !== 'sqlite' || empty($options['primary_key'])) {
            return;
        }

        $primaryKey = $options['primary_key'];
        if (!is_array($primaryKey)) {
            $primaryKey = [$primaryKey];
        }
        $primaryKey = array_flip($primaryKey);

        /** @var \Cake\Collection\Collection $columnsCollection */
        $columnsCollection = (new Collection($this->actions->getActions()))
            ->filter(function ($action) {
                return $action instanceof AddColumn;
            })
            ->map(function ($action) {
                /** @var \Migrations\Db\Action\ChangeColumn|\Migrations\Db\Action\RenameColumn|\Migrations\Db\Action\RemoveColumn|\Migrations\Db\Action\AddColumn $action */
                return $action->getColumn();
            });
        $primaryKeyColumns = $columnsCollection->filter(function (Column $columnDef, $key) use ($primaryKey) {
            return isset($primaryKey[$columnDef->getName()]);
        })->toArray();

        if (!$primaryKeyColumns) {
            return;
        }

        foreach ($primaryKeyColumns as $primaryKeyColumn) {
            if ($primaryKeyColumn->isIdentity()) {
                unset($primaryKey[$primaryKeyColumn->getName()]);
            }
        }

        $primaryKey = array_flip($primaryKey);

        if ($primaryKey) {
            $options['primary_key'] = $primaryKey;
        } else {
            unset($options['primary_key']);
        }

        $this->getTable()->setOptions($options);
    }

    /**
     * Updates a table from the object instance.
     *
     * @return void
     */
    public function update(): void
    {
        $this->executeActions(true);
        $this->saveData();
        $this->reset(); // reset pending changes
    }

    /**
     * Commit the pending data waiting for insertion.
     *
     * @return void
     */
    public function saveData(): void
    {
        $rows = $this->getData();
        if (!$rows) {
            return;
        }

        $bulk = true;
        $row = current($rows);
        $c = array_keys($row);
        foreach ($this->getData() as $row) {
            $k = array_keys($row);
            if ($k !== $c) {
                $bulk = false;
                break;
            }
        }

        if ($bulk) {
            $this->getAdapter()->bulkinsert(
                $this->table,
                $this->getData(),
                $this->insertMode,
                $this->upsertUpdateColumns,
                $this->upsertConflictColumns,
            );
        } else {
            foreach ($this->getData() as $row) {
                $this->getAdapter()->insert(
                    $this->table,
                    $row,
                    $this->insertMode,
                    $this->upsertUpdateColumns,
                    $this->upsertConflictColumns,
                );
            }
        }

        $this->resetData();
        $this->insertMode = null;
        $this->upsertUpdateColumns = null;
        $this->upsertConflictColumns = null;
    }

    /**
     * Immediately truncates the table. This operation cannot be undone
     *
     * @return void
     */
    public function truncate(): void
    {
        $this->getAdapter()->truncateTable($this->getName());
    }

    /**
     * Commits the table changes.
     *
     * If the table doesn't exist it is created otherwise it is updated.
     *
     * @return void
     */
    public function save(): void
    {
        if ($this->exists()) {
            $this->update(); // update the table
        } else {
            $this->create(); // create the table
        }
    }

    /**
     * Executes all the pending actions for this table
     *
     * @param bool $exists Whether the table existed prior to executing this method
     * @return void
     */
    protected function executeActions(bool $exists): void
    {
        // Renaming a table is tricky, specially when running a reversible migration
        // down. We will just assume the table already exists if the user commands a
        // table rename.
        if (!$exists) {
            foreach ($this->actions->getActions() as $action) {
                if ($action instanceof RenameTable) {
                    $exists = true;
                    break;
                }
            }
        }

        // If table exists and has partition configuration, create SetPartitioning action
        if ($exists) {
            $partition = $this->table->getPartition();
            if ($partition !== null && $partition->getDefinitions()) {
                $this->actions->addAction(new SetPartitioning($this->table, $partition));
            }
        }

        // If the table does not exist, the last command in the chain needs to be
        // a CreateTable action.
        if (!$exists) {
            $this->actions->addAction(new CreateTable($this->table));
        }

        $plan = new Plan($this->actions);
        $plan->execute($this->getAdapter());
    }

    /**
     * Merges existing column options with new options.
     * Only attributes that are explicitly specified in the new options will override existing ones.
     *
     * @param \Migrations\Db\Table\Column $existingColumn Existing column definition
     * @param string $newColumnType New column type
     * @param array<string, mixed> $options New options
     * @return array<string, mixed> Merged options
     */
    protected function mergeColumnOptions(Column $existingColumn, string $newColumnType, array $options): array
    {
        // Determine if type is changing
        $newTypeString = (string)$newColumnType;
        $existingTypeString = (string)$existingColumn->getType();
        $typeChanging = $newTypeString !== $existingTypeString;

        // Build array of existing column attributes
        $existingOptions = [];

        // Only preserve limit if type is not changing or limit is not explicitly set
        if (!$typeChanging && !array_key_exists('limit', $options) && !array_key_exists('length', $options)) {
            $limit = $existingColumn->getLimit();
            if ($limit !== null) {
                $existingOptions['limit'] = $limit;
            }
        }

        // Preserve default if not explicitly set
        if (!array_key_exists('default', $options)) {
            $existingOptions['default'] = $existingColumn->getDefault();
        }

        // Preserve null if not explicitly set
        if (!isset($options['null'])) {
            $existingOptions['null'] = $existingColumn->getNull();
        }

        // Preserve scale/precision if not explicitly set
        if (!array_key_exists('scale', $options) && !array_key_exists('precision', $options)) {
            $scale = $existingColumn->getScale();
            if ($scale !== null) {
                $existingOptions['scale'] = $scale;
            }
            $precision = $existingColumn->getPrecision();
            if ($precision !== null) {
                $existingOptions['precision'] = $precision;
            }
        }

        // Preserve comment if not explicitly set
        if (!array_key_exists('comment', $options)) {
            $comment = $existingColumn->getComment();
            if ($comment !== null) {
                $existingOptions['comment'] = $comment;
            }
        }

        // Preserve signed if not explicitly set (always has a value)
        if (!isset($options['signed'])) {
            $existingOptions['signed'] = $existingColumn->getSigned();
        }

        // Preserve collation if not explicitly set
        if (!isset($options['collation'])) {
            $collation = $existingColumn->getCollation();
            if ($collation !== null) {
                $existingOptions['collation'] = $collation;
            }
        }

        // Preserve encoding if not explicitly set
        if (!isset($options['encoding'])) {
            $encoding = $existingColumn->getEncoding();
            if ($encoding !== null) {
                $existingOptions['encoding'] = $encoding;
            }
        }

        // Note: enum/set values are not preserved as schema reflection doesn't populate them

        // New options override existing ones
        return array_merge($existingOptions, $options);
    }
}
