<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use Cake\Console\ConsoleIo;
use Cake\Database\Connection;
use Cake\Database\Query;
use Cake\Database\Query\DeleteQuery;
use Cake\Database\Query\InsertQuery;
use Cake\Database\Query\SelectQuery;
use Cake\Database\Query\UpdateQuery;
use Migrations\Db\InsertMode;
use Migrations\Db\Table\CheckConstraint;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\TableMetadata;
use Migrations\MigrationInterface;
use Migrations\SeedInterface;

/**
 * Adapter Wrapper.
 *
 * Proxy commands through to another adapter, allowing modification of
 * parameters during calls.
 */
abstract class AdapterWrapper implements WrapperInterface
{
    /**
     * @var \Migrations\Db\Adapter\AdapterInterface
     */
    protected AdapterInterface $adapter;

    /**
     * @inheritDoc
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->setAdapter($adapter);
    }

    /**
     * @inheritDoc
     */
    public function setAdapter(AdapterInterface $adapter): AdapterInterface
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options): AdapterInterface
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
    public function getColumnForType(string $columnName, string $type, array $options): Column
    {
        return $this->adapter->getColumnForType($columnName, $type, $options);
    }

    /**
     * @inheritDoc
     */
    public function connect(): void
    {
        $this->getAdapter()->connect();
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void
    {
        $this->getAdapter()->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->getAdapter()->execute($sql, $params);
    }

    /**
     * @inheritDoc
     */
    public function query(string $sql, array $params = []): mixed
    {
        return $this->getAdapter()->query($sql, $params);
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
        $this->getAdapter()->insert($table, $row, $mode, $updateColumns, $conflictColumns);
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
        $this->getAdapter()->bulkinsert($table, $rows, $mode, $updateColumns, $conflictColumns);
    }

    /**
     * @inheritDoc
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->getAdapter()->fetchRow($sql);
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(string $sql): array
    {
        return $this->getAdapter()->fetchAll($sql);
    }

    /**
     * @inheritDoc
     */
    public function getVersions(): array
    {
        return $this->getAdapter()->getVersions();
    }

    /**
     * @inheritDoc
     */
    public function getVersionLog(): array
    {
        return $this->getAdapter()->getVersionLog();
    }

    /**
     * @inheritDoc
     */
    public function cleanupMissing(array $missingVersions): void
    {
        $this->getAdapter()->cleanupMissing($missingVersions);
    }

    /**
     * @inheritDoc
     */
    public function migrated(MigrationInterface $migration, string $direction, string $startTime, string $endTime): AdapterInterface
    {
        $this->getAdapter()->migrated($migration, $direction, $startTime, $endTime);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function toggleBreakpoint(MigrationInterface $migration): AdapterInterface
    {
        $this->getAdapter()->toggleBreakpoint($migration);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resetAllBreakpoints(): int
    {
        return $this->getAdapter()->resetAllBreakpoints();
    }

    /**
     * @inheritDoc
     */
    public function setBreakpoint(MigrationInterface $migration): AdapterInterface
    {
        $this->getAdapter()->setBreakpoint($migration);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function unsetBreakpoint(MigrationInterface $migration): AdapterInterface
    {
        $this->getAdapter()->unsetBreakpoint($migration);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function createSchemaTable(): void
    {
        $this->getAdapter()->createSchemaTable();
    }

    /**
     * @inheritDoc
     */
    public function createSeedSchemaTable(): void
    {
        $this->getAdapter()->createSeedSchemaTable();
    }

    /**
     * @inheritDoc
     */
    public function getSeedSchemaTableName(): string
    {
        return $this->getAdapter()->getSeedSchemaTableName();
    }

    /**
     * @inheritDoc
     */
    public function getSeedLog(): array
    {
        return $this->getAdapter()->getSeedLog();
    }

    /**
     * @inheritDoc
     */
    public function seedExecuted(SeedInterface $seed, string $executedTime): AdapterInterface
    {
        $this->getAdapter()->seedExecuted($seed, $executedTime);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function removeSeedFromLog(SeedInterface $seed): AdapterInterface
    {
        $this->getAdapter()->removeSeedFromLog($seed);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getColumnTypes(): array
    {
        return $this->getAdapter()->getColumnTypes();
    }

    /**
     * @inheritDoc
     */
    public function isValidColumnType(Column $column): bool
    {
        return $this->getAdapter()->isValidColumnType($column);
    }

    /**
     * @inheritDoc
     */
    public function hasTransactions(): bool
    {
        return $this->getAdapter()->hasTransactions();
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): void
    {
        $this->getAdapter()->beginTransaction();
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction(): void
    {
        $this->getAdapter()->commitTransaction();
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction(): void
    {
        $this->getAdapter()->rollbackTransaction();
    }

    /**
     * @inheritDoc
     */
    public function quoteTableName(string $tableName): string
    {
        return $this->getAdapter()->quoteTableName($tableName);
    }

    /**
     * @inheritDoc
     */
    public function quoteColumnName(string $columnName): string
    {
        return $this->getAdapter()->quoteColumnName($columnName);
    }

    /**
     * @inheritDoc
     */
    public function hasTable(string $tableName): bool
    {
        return $this->getAdapter()->hasTable($tableName);
    }

    /**
     * @inheritDoc
     */
    public function createTable(TableMetadata $table, array $columns = [], array $indexes = []): void
    {
        $this->getAdapter()->createTable($table, $columns, $indexes);
    }

    /**
     * @inheritDoc
     */
    public function getColumns(string $tableName): array
    {
        return $this->getAdapter()->getColumns($tableName);
    }

    /**
     * @inheritDoc
     */
    public function hasColumn(string $tableName, string $columnName): bool
    {
        return $this->getAdapter()->hasColumn($tableName, $columnName);
    }

    /**
     * @inheritDoc
     */
    public function hasIndex(string $tableName, string|array $columns): bool
    {
        return $this->getAdapter()->hasIndex($tableName, $columns);
    }

    /**
     * @inheritDoc
     */
    public function hasIndexByName(string $tableName, string $indexName): bool
    {
        return $this->getAdapter()->hasIndexByName($tableName, $indexName);
    }

    /**
     * @inheritDoc
     */
    public function hasPrimaryKey(string $tableName, $columns, ?string $constraint = null): bool
    {
        return $this->getAdapter()->hasPrimaryKey($tableName, $columns, $constraint);
    }

    /**
     * @inheritDoc
     */
    public function hasForeignKey(string $tableName, $columns, ?string $constraint = null): bool
    {
        return $this->getAdapter()->hasForeignKey($tableName, $columns, $constraint);
    }

    /**
     * @inheritDoc
     */
    public function createDatabase(string $name, array $options = []): void
    {
        $this->getAdapter()->createDatabase($name, $options);
    }

    /**
     * @inheritDoc
     */
    public function hasDatabase(string $name): bool
    {
        return $this->getAdapter()->hasDatabase($name);
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase(string $name): void
    {
        $this->getAdapter()->dropDatabase($name);
    }

    /**
     * @inheritDoc
     */
    public function createSchema(string $schemaName = 'public'): void
    {
        $this->getAdapter()->createSchema($schemaName);
    }

    /**
     * @inheritDoc
     */
    public function dropSchema(string $schemaName): void
    {
        $this->getAdapter()->dropSchema($schemaName);
    }

    /**
     * @inheritDoc
     */
    public function truncateTable(string $tableName): void
    {
        $this->getAdapter()->truncateTable($tableName);
    }

    /**
     * @inheritDoc
     */
    public function castToBool($value): mixed
    {
        return $this->getAdapter()->castToBool($value);
    }

    /**
     * @return \Cake\Database\Connection
     */
    public function getConnection(): Connection
    {
        return $this->getAdapter()->getConnection();
    }

    /**
     * @inheritDoc
     */
    public function executeActions(TableMetadata $table, array $actions): void
    {
        $this->getAdapter()->executeActions($table, $actions);
    }

    /**
     * @inheritDoc
     */
    public function getQueryBuilder(string $type): Query
    {
        return $this->getAdapter()->getQueryBuilder($type);
    }

    /**
     * @inheritDoc
     */
    public function getSelectBuilder(): SelectQuery
    {
        return $this->getAdapter()->getSelectBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getInsertBuilder(): InsertQuery
    {
        return $this->getAdapter()->getInsertBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getUpdateBuilder(): UpdateQuery
    {
        return $this->getAdapter()->getUpdateBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getDeleteBuilder(): DeleteQuery
    {
        return $this->getAdapter()->getDeleteBuilder();
    }

    /**
     * @inheritDoc
     */
    public function hasCheckConstraint(string $tableName, string $constraintName): bool
    {
        return $this->getAdapter()->hasCheckConstraint($tableName, $constraintName);
    }

    /**
     * @inheritDoc
     */
    public function addCheckConstraint(TableMetadata $table, CheckConstraint $checkConstraint): void
    {
        $this->getAdapter()->addCheckConstraint($table, $checkConstraint);
    }

    /**
     * @inheritDoc
     */
    public function dropCheckConstraint(string $tableName, string $constraintName): void
    {
        $this->getAdapter()->dropCheckConstraint($tableName, $constraintName);
    }

    /**
     * @inheritDoc
     */
    public function setIo(ConsoleIo $io)
    {
        $this->getAdapter()->setIo($io);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIo(): ?ConsoleIo
    {
        return $this->getAdapter()->getIo();
    }

    /**
     * @inheritDoc
     */
    public function getSchemaTableName(): string
    {
        return $this->getAdapter()->getSchemaTableName();
    }
}
