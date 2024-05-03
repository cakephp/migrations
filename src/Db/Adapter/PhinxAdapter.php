<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use Cake\Database\Connection;
use Cake\Database\Query;
use Cake\Database\Query\DeleteQuery;
use Cake\Database\Query\InsertQuery;
use Cake\Database\Query\SelectQuery;
use Cake\Database\Query\UpdateQuery;
use Migrations\Db\Action\Action;
use Migrations\Db\Action\AddColumn;
use Migrations\Db\Action\AddForeignKey;
use Migrations\Db\Action\AddIndex;
use Migrations\Db\Action\ChangeColumn;
use Migrations\Db\Action\ChangeComment;
use Migrations\Db\Action\ChangePrimaryKey;
use Migrations\Db\Action\CreateTable;
use Migrations\Db\Action\DropForeignKey;
use Migrations\Db\Action\DropIndex;
use Migrations\Db\Action\DropTable;
use Migrations\Db\Action\RemoveColumn;
use Migrations\Db\Action\RenameColumn;
use Migrations\Db\Action\RenameTable;
use Migrations\Db\Literal;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\Table;
use Phinx\Db\Action\Action as PhinxAction;
use Phinx\Db\Action\AddColumn as PhinxAddColumn;
use Phinx\Db\Action\AddForeignKey as PhinxAddForeignKey;
use Phinx\Db\Action\AddIndex as PhinxAddIndex;
use Phinx\Db\Action\ChangeColumn as PhinxChangeColumn;
use Phinx\Db\Action\ChangeComment as PhinxChangeComment;
use Phinx\Db\Action\ChangePrimaryKey as PhinxChangePrimaryKey;
use Phinx\Db\Action\CreateTable as PhinxCreateTable;
use Phinx\Db\Action\DropForeignKey as PhinxDropForeignKey;
use Phinx\Db\Action\DropIndex as PhinxDropIndex;
use Phinx\Db\Action\DropTable as PhinxDropTable;
use Phinx\Db\Action\RemoveColumn as PhinxRemoveColumn;
use Phinx\Db\Action\RenameColumn as PhinxRenameColumn;
use Phinx\Db\Action\RenameTable as PhinxRenameTable;
use Phinx\Db\Adapter\AdapterInterface as PhinxAdapterInterface;
use Phinx\Db\Table\Column as PhinxColumn;
use Phinx\Db\Table\ForeignKey as PhinxForeignKey;
use Phinx\Db\Table\Index as PhinxIndex;
use Phinx\Db\Table\Table as PhinxTable;
use Phinx\Migration\MigrationInterface;
use Phinx\Util\Literal as PhinxLiteral;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Adapter to provide a Phinx\Adapter\AdapterInterface that
 * is wrapping Migrations\Db\AdapterInterface.
 *
 * This bridge is necessary to run migrations with both phinx and migrations
 * engines.
 */
class PhinxAdapter implements PhinxAdapterInterface
{
    /**
     * @var \Migrations\Db\Adapter\AdapterInterface
     */
    protected AdapterInterface $adapter;

    /**
     * Convert a phinx table to a migrations one
     *
     * @param \Phinx\Db\Table\Table $phinxTable The table to convert.
     * @return \Migrations\Db\Table\Table
     */
    protected function convertTable(PhinxTable $phinxTable): Table
    {
        $table = new Table(
            $phinxTable->getName(),
            $phinxTable->getOptions(),
        );

        return $table;
    }

    /**
     * Convert a phinx column into a migrations object
     *
     * @param \Phinx\Db\Table\Column $phinxColumn The column to convert.
     * @return \Migrations\Db\Table\Column
     */
    protected function convertColumn(PhinxColumn $phinxColumn): Column
    {
        $column = new Column();
        $attrs = [
            'name', 'null', 'default', 'identity',
            'generated', 'seed', 'increment', 'scale',
            'after', 'update', 'comment', 'signed',
            'timezone', 'properties', 'collation',
            'encoding', 'srid', 'values', 'limit',
        ];
        foreach ($attrs as $attr) {
            $get = 'get' . ucfirst($attr);
            $set = 'set' . ucfirst($attr);
            try {
                $value = $phinxColumn->{$get}();
            } catch (RuntimeException $e) {
                $value = null;
            }
            if ($value !== null) {
                $column->{$set}($value);
            }
        }
        try {
            $type = $phinxColumn->getType();
        } catch (RuntimeException $e) {
            $type = null;
        }
        if ($type instanceof PhinxLiteral) {
            $type = Literal::from((string)$type);
        }
        if ($type) {
            $column->setType($type);
        }

        return $column;
    }

    /**
     * Convert a migrations column into a phinx object
     *
     * @param \Migrations\Db\Table\Column $column The column to convert.
     * @return \Phinx\Db\Table\Column
     */
    protected function convertColumnToPhinx(Column $column): PhinxColumn
    {
        $phinx = new PhinxColumn();
        $attrs = [
            'name', 'type', 'null', 'default', 'identity',
            'generated', 'seed', 'increment', 'scale',
            'after', 'update', 'comment', 'signed',
            'timezone', 'properties', 'collation',
            'encoding', 'srid', 'values', 'limit',
        ];
        foreach ($attrs as $attr) {
            $get = 'get' . ucfirst($attr);
            $set = 'set' . ucfirst($attr);
            $value = $column->{$get}();
            $value = $column->{$get}();
            if ($value !== null) {
                $phinx->{$set}($value);
            }
        }

        return $phinx;
    }

    /**
     * Convert a migrations Index into a phinx object
     *
     * @param \Phinx\Db\Table\Index $phinxIndex The index to convert.
     * @return \Migrations\Db\Table\Index
     */
    protected function convertIndex(PhinxIndex $phinxIndex): Index
    {
        $index = new Index();
        $attrs = [
            'name', 'columns', 'type', 'limit', 'order',
            'include',
        ];
        foreach ($attrs as $attr) {
            $get = 'get' . ucfirst($attr);
            $set = 'set' . ucfirst($attr);
            try {
                $value = $phinxIndex->{$get}();
            } catch (RuntimeException $e) {
                $value = null;
            }
            if ($value !== null) {
                $index->{$set}($value);
            }
        }

        return $index;
    }

    /**
     * Convert a phinx ForeignKey into a migrations object
     *
     * @param \Phinx\Db\Table\ForeignKey $phinxKey The index to convert.
     * @return \Migrations\Db\Table\ForeignKey
     */
    protected function convertForeignKey(PhinxForeignKey $phinxKey): ForeignKey
    {
        $foreignkey = new ForeignKey();
        $attrs = [
            'columns', 'referencedColumns', 'onDelete', 'onUpdate', 'constraint',
        ];

        foreach ($attrs as $attr) {
            $get = 'get' . ucfirst($attr);
            $set = 'set' . ucfirst($attr);
            try {
                $value = $phinxKey->{$get}();
            } catch (RuntimeException $e) {
                $value = null;
            }
            if ($value !== null) {
                $foreignkey->{$set}($value);
            }
        }

        try {
            $referenced = $phinxKey->getReferencedTable();
        } catch (RuntimeException $e) {
            $referenced = null;
        }
        if ($referenced) {
            $foreignkey->setReferencedTable($this->convertTable($referenced));
        }

        return $foreignkey;
    }

    /**
     * Convert a phinx Action into a migrations object
     *
     * @param \Phinx\Db\Action\Action $phinxAction The index to convert.
     * @return \Migrations\Db\Action\Action
     */
    protected function convertAction(PhinxAction $phinxAction): Action
    {
        $action = null;
        if ($phinxAction instanceof PhinxAddColumn) {
            $action = new AddColumn(
                $this->convertTable($phinxAction->getTable()),
                $this->convertColumn($phinxAction->getColumn())
            );
        } elseif ($phinxAction instanceof PhinxAddForeignKey) {
            $action = new AddForeignKey(
                $this->convertTable($phinxAction->getTable()),
                $this->convertForeignKey($phinxAction->getForeignKey())
            );
        } elseif ($phinxAction instanceof PhinxAddIndex) {
            $action = new AddIndex(
                $this->convertTable($phinxAction->getTable()),
                $this->convertIndex($phinxAction->getIndex())
            );
        } elseif ($phinxAction instanceof PhinxChangeColumn) {
            $action = new ChangeColumn(
                $this->convertTable($phinxAction->getTable()),
                $phinxAction->getColumnName(),
                $this->convertColumn($phinxAction->getColumn())
            );
        } elseif ($phinxAction instanceof PhinxChangeComment) {
            $action = new ChangeComment(
                $this->convertTable($phinxAction->getTable()),
                $phinxAction->getNewComment()
            );
        } elseif ($phinxAction instanceof PhinxChangePrimaryKey) {
            $action = new ChangePrimaryKey(
                $this->convertTable($phinxAction->getTable()),
                $phinxAction->getNewColumns()
            );
        } elseif ($phinxAction instanceof PhinxCreateTable) {
            $action = new CreateTable(
                $this->convertTable($phinxAction->getTable()),
            );
        } elseif ($phinxAction instanceof PhinxDropForeignKey) {
            $action = new DropForeignKey(
                $this->convertTable($phinxAction->getTable()),
                $this->convertForeignKey($phinxAction->getForeignKey()),
            );
        } elseif ($phinxAction instanceof PhinxDropIndex) {
            $action = new DropIndex(
                $this->convertTable($phinxAction->getTable()),
                $this->convertIndex($phinxAction->getIndex())
            );
        } elseif ($phinxAction instanceof PhinxDropTable) {
            $action = new DropTable(
                $this->convertTable($phinxAction->getTable()),
            );
        } elseif ($phinxAction instanceof PhinxRemoveColumn) {
            $action = new RemoveColumn(
                $this->convertTable($phinxAction->getTable()),
                $this->convertColumn($phinxAction->getColumn())
            );
        } elseif ($phinxAction instanceof PhinxRenameColumn) {
            $action = new RenameColumn(
                $this->convertTable($phinxAction->getTable()),
                $this->convertColumn($phinxAction->getColumn()),
                $phinxAction->getNewName(),
            );
        } elseif ($phinxAction instanceof PhinxRenameTable) {
            $action = new RenameTable(
                $this->convertTable($phinxAction->getTable()),
                $phinxAction->getNewName()
            );
        }
        if (!$action) {
            throw new RuntimeException('Unable to map action of type ' . get_class($phinxAction));
        }

        return $action;
    }

    /**
     * Convert a phinx Literal into a migrations object
     *
     * @param \Phinx\Util\Literal|string $phinxLiteral The literal to convert.
     * @return \Migrations\Db\Literal|string
     */
    protected function convertLiteral(PhinxLiteral|string $phinxLiteral): Literal|string
    {
        if (is_string($phinxLiteral)) {
            return $phinxLiteral;
        }

        return new Literal((string)$phinxLiteral);
    }

    /**
     * @inheritDoc
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options): PhinxAdapterInterface
    {
        $this->adapter->setOptions($options);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): array
    {
        return $this->adapter->getOptions();
    }

    /**
     * @inheritDoc
     */
    public function hasOption(string $name): bool
    {
        return $this->adapter->hasOption($name);
    }

    /**
     * @inheritDoc
     */
    public function getOption(string $name): mixed
    {
        return $this->adapter->getOption($name);
    }

    /**
     * @inheritDoc
     */
    public function setInput(InputInterface $input): PhinxAdapterInterface
    {
        throw new RuntimeException('Using setInput() on Adapters is no longer supported');
    }

    /**
     * @inheritDoc
     */
    public function getInput(): InputInterface
    {
        throw new RuntimeException('Using getInput() on Adapters is no longer supported');
    }

    /**
     * @inheritDoc
     */
    public function setOutput(OutputInterface $output): PhinxAdapterInterface
    {
        throw new RuntimeException('Using setOutput() on Adapters is no longer supported');
    }

    /**
     * @inheritDoc
     */
    public function getOutput(): OutputInterface
    {
        throw new RuntimeException('Using getOutput() on Adapters is no longer supported');
    }

    /**
     * @inheritDoc
     */
    public function getColumnForType(string $columnName, string $type, array $options): PhinxColumn
    {
        $column = $this->adapter->getColumnForType($columnName, $type, $options);

        return $this->convertColumnToPhinx($column);
    }

    /**
     * @inheritDoc
     */
    public function connect(): void
    {
        $this->adapter->connect();
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void
    {
        $this->adapter->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->adapter->execute($sql, $params);
    }

    /**
     * @inheritDoc
     */
    public function query(string $sql, array $params = []): mixed
    {
        return $this->adapter->query($sql, $params);
    }

    /**
     * @inheritDoc
     */
    public function insert(PhinxTable $table, array $row): void
    {
        $this->adapter->insert($this->convertTable($table), $row);
    }

    /**
     * @inheritDoc
     */
    public function bulkinsert(PhinxTable $table, array $rows): void
    {
        $this->adapter->bulkinsert($this->convertTable($table), $rows);
    }

    /**
     * @inheritDoc
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->adapter->fetchRow($sql);
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(string $sql): array
    {
        return $this->adapter->fetchAll($sql);
    }

    /**
     * @inheritDoc
     */
    public function getVersions(): array
    {
        return $this->adapter->getVersions();
    }

    /**
     * @inheritDoc
     */
    public function getVersionLog(): array
    {
        return $this->adapter->getVersionLog();
    }

    /**
     * @inheritDoc
     */
    public function migrated(MigrationInterface $migration, string $direction, string $startTime, string $endTime): PhinxAdapterInterface
    {
        $this->adapter->migrated($migration, $direction, $startTime, $endTime);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function toggleBreakpoint(MigrationInterface $migration): PhinxAdapterInterface
    {
        $this->adapter->toggleBreakpoint($migration);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resetAllBreakpoints(): int
    {
        return $this->adapter->resetAllBreakpoints();
    }

    /**
     * @inheritDoc
     */
    public function setBreakpoint(MigrationInterface $migration): PhinxAdapterInterface
    {
        $this->adapter->setBreakpoint($migration);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function unsetBreakpoint(MigrationInterface $migration): PhinxAdapterInterface
    {
        $this->adapter->unsetBreakpoint($migration);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function createSchemaTable(): void
    {
        $this->adapter->createSchemaTable();
    }

    /**
     * @inheritDoc
     */
    public function getColumnTypes(): array
    {
        return $this->adapter->getColumnTypes();
    }

    /**
     * @inheritDoc
     */
    public function isValidColumnType(PhinxColumn $column): bool
    {
        return $this->adapter->isValidColumnType($this->convertColumn($column));
    }

    /**
     * @inheritDoc
     */
    public function hasTransactions(): bool
    {
        return $this->adapter->hasTransactions();
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): void
    {
        $this->adapter->beginTransaction();
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction(): void
    {
        $this->adapter->commitTransaction();
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction(): void
    {
        $this->adapter->rollbackTransaction();
    }

    /**
     * @inheritDoc
     */
    public function quoteTableName(string $tableName): string
    {
        return $this->adapter->quoteTableName($tableName);
    }

    /**
     * @inheritDoc
     */
    public function quoteColumnName(string $columnName): string
    {
        return $this->adapter->quoteColumnName($columnName);
    }

    /**
     * @inheritDoc
     */
    public function hasTable(string $tableName): bool
    {
        return $this->adapter->hasTable($tableName);
    }

    /**
     * @inheritDoc
     */
    public function createTable(PhinxTable $table, array $columns = [], array $indexes = []): void
    {
        $columns = array_map(function ($col) {
            return $this->convertColumn($col);
        }, $columns);
        $indexes = array_map(function ($ind) {
            return $this->convertIndex($ind);
        }, $indexes);
        $this->adapter->createTable($this->convertTable($table), $columns, $indexes);
    }

    /**
     * @inheritDoc
     */
    public function getColumns(string $tableName): array
    {
        $columns = $this->adapter->getColumns($tableName);

        return array_map(function ($col) {
            return $this->convertColumnToPhinx($col);
        }, $columns);
    }

    /**
     * @inheritDoc
     */
    public function hasColumn(string $tableName, string $columnName): bool
    {
        return $this->adapter->hasColumn($tableName, $columnName);
    }

    /**
     * @inheritDoc
     */
    public function hasIndex(string $tableName, string|array $columns): bool
    {
        return $this->adapter->hasIndex($tableName, $columns);
    }

    /**
     * @inheritDoc
     */
    public function hasIndexByName(string $tableName, string $indexName): bool
    {
        return $this->adapter->hasIndexByName($tableName, $indexName);
    }

    /**
     * @inheritDoc
     */
    public function hasPrimaryKey(string $tableName, $columns, ?string $constraint = null): bool
    {
        return $this->adapter->hasPrimaryKey($tableName, $columns, $constraint);
    }

    /**
     * @inheritDoc
     */
    public function hasForeignKey(string $tableName, $columns, ?string $constraint = null): bool
    {
        return $this->adapter->hasForeignKey($tableName, $columns, $constraint);
    }

    /**
     * @inheritDoc
     */
    public function getSqlType(PhinxLiteral|string $type, ?int $limit = null): array
    {
        return $this->adapter->getSqlType($this->convertLiteral($type), $limit);
    }

    /**
     * @inheritDoc
     */
    public function createDatabase(string $name, array $options = []): void
    {
        $this->adapter->createDatabase($name, $options);
    }

    /**
     * @inheritDoc
     */
    public function hasDatabase(string $name): bool
    {
        return $this->adapter->hasDatabase($name);
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase(string $name): void
    {
        $this->adapter->dropDatabase($name);
    }

    /**
     * @inheritDoc
     */
    public function createSchema(string $schemaName = 'public'): void
    {
        $this->adapter->createSchema($schemaName);
    }

    /**
     * @inheritDoc
     */
    public function dropSchema(string $schemaName): void
    {
        $this->adapter->dropSchema($schemaName);
    }

    /**
     * @inheritDoc
     */
    public function truncateTable(string $tableName): void
    {
        $this->adapter->truncateTable($tableName);
    }

    /**
     * @inheritDoc
     */
    public function castToBool($value): mixed
    {
        return $this->adapter->castToBool($value);
    }

    /**
     * @return \Cake\Database\Connection
     */
    public function getConnection(): Connection
    {
        return $this->adapter->getConnection();
    }

    /**
     * @inheritDoc
     */
    public function executeActions(PhinxTable $table, array $actions): void
    {
        $actions = array_map(function ($act) {
            return $this->convertAction($act);
        }, $actions);
        $this->adapter->executeActions($this->convertTable($table), $actions);
    }

    /**
     * @inheritDoc
     */
    public function getAdapterType(): string
    {
        return $this->adapter->getAdapterType();
    }

    /**
     * @inheritDoc
     */
    public function getQueryBuilder(string $type): Query
    {
        return $this->adapter->getQueryBuilder($type);
    }

    /**
     * @inheritDoc
     */
    public function getSelectBuilder(): SelectQuery
    {
        return $this->adapter->getSelectBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getInsertBuilder(): InsertQuery
    {
        return $this->adapter->getInsertBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getUpdateBuilder(): UpdateQuery
    {
        return $this->adapter->getUpdateBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getDeleteBuilder(): DeleteQuery
    {
        return $this->adapter->getDeleteBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getCakeConnection(): Connection
    {
        return $this->adapter->getConnection();
    }
}
