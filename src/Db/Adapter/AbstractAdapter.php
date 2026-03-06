<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use BadMethodCallException;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Query;
use Cake\Database\Query\DeleteQuery;
use Cake\Database\Query\InsertQuery;
use Cake\Database\Query\SelectQuery;
use Cake\Database\Query\UpdateQuery;
use Cake\Database\Schema\SchemaDialect;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Exception;
use InvalidArgumentException;
use Migrations\Config\Config;
use Migrations\Db\Action\AddColumn;
use Migrations\Db\Action\AddForeignKey;
use Migrations\Db\Action\AddIndex;
use Migrations\Db\Action\AddPartition;
use Migrations\Db\Action\ChangeColumn;
use Migrations\Db\Action\ChangeComment;
use Migrations\Db\Action\ChangePrimaryKey;
use Migrations\Db\Action\DropForeignKey;
use Migrations\Db\Action\DropIndex;
use Migrations\Db\Action\DropPartition;
use Migrations\Db\Action\DropTable;
use Migrations\Db\Action\RemoveColumn;
use Migrations\Db\Action\RenameColumn;
use Migrations\Db\Action\RenameTable;
use Migrations\Db\Action\SetPartitioning;
use Migrations\Db\AlterInstructions;
use Migrations\Db\InsertMode;
use Migrations\Db\Literal;
use Migrations\Db\Table;
use Migrations\Db\Table\CheckConstraint;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\Partition;
use Migrations\Db\Table\TableMetadata;
use Migrations\MigrationInterface;
use Migrations\SeedInterface;
use PDOException;
use RuntimeException;
use function Cake\Core\deprecationWarning;

/**
 * Base Abstract Database Adapter.
 */
abstract class AbstractAdapter implements AdapterInterface, DirectActionInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * @var \Cake\Console\ConsoleIo
     */
    protected ConsoleIo $io;

    /**
     * @var string[]
     */
    protected array $createdTables = [];

    /**
     * @var string
     */
    protected string $schemaTableName = 'phinxlog';

    /**
     * @var string
     */
    protected string $seedSchemaTableName = 'cake_seeds';

    /**
     * @var array
     */
    protected array $dataDomain = [];

    /**
     * @var \Cake\Database\Connection|null
     */
    protected ?Connection $connection = null;

    /**
     * Class Constructor.
     *
     * @param array<string, mixed> $options Options
     * @param \Cake\Console\ConsoleIo|null $io Console input/output
     */
    public function __construct(array $options, ?ConsoleIo $io = null)
    {
        $this->setOptions($options);
        if ($io !== null) {
            $this->setIo($io);
        }
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options): AdapterInterface
    {
        $this->options = $options;

        if (isset($options['migration_table'])) {
            $this->setSchemaTableName($options['migration_table']);
        }

        if (isset($options['seed_table'])) {
            $this->setSeedSchemaTableName($options['seed_table']);
        }

        if (isset($options['connection']) && $options['connection'] instanceof Connection) {
            $this->setConnection($options['connection']);
        }

        return $this;
    }

    /**
     * Sets the database connection.
     *
     * @param \Cake\Database\Connection $connection Connection
     * @return \Migrations\Db\Adapter\AdapterInterface
     */
    public function setConnection(Connection $connection): AdapterInterface
    {
        $this->connection = $connection;

        // Create the schema table if it doesn't already exist
        if (!$this->hasTable($this->getSchemaTableName())) {
            $this->createSchemaTable();
        } else {
            $this->migrationsTable()->upgradeTable();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @inheritDoc
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * @inheritDoc
     */
    public function getOption(string $name): mixed
    {
        if (!$this->hasOption($name)) {
            return null;
        }

        return $this->options[$name];
    }

    /**
     * Get the schema dialect for this adapter.
     *
     * @return \Cake\Database\Schema\SchemaDialect
     */
    protected function getSchemaDialect(): SchemaDialect
    {
        $driver = $this->getConnection()->getDriver();

        return $driver->schemaDialect();
    }

    /**
     * Gets the database connection
     *
     * @return \Cake\Database\Connection
     */
    public function getConnection(): Connection
    {
        if ($this->connection === null) {
            $this->connection = $this->getOption('connection');
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Backwards compatibility shim for migrations 3.x
     *
     * @return \Cake\Database\Connection
     * @deprecated 4.8.0 Use getConnection() instead.
     */
    public function getDecoratedConnection(): Connection
    {
        deprecationWarning(
            '4.8.0',
            'Using getDecoratedConnection() is deprecated. Use getConnection() instead.',
        );

        return $this->getConnection();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    public function connect(): void
    {
        $this->getConnection()->getDriver()->connect();
        $this->setConnection($this->getConnection());
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void
    {
        $this->getConnection()->getDriver()->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): void
    {
        $this->getConnection()->begin();
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction(): void
    {
        $this->getConnection()->commit();
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction(): void
    {
        $this->getConnection()->rollback();
    }

    /**
     * @inheritDoc
     */
    public function hasTransactions(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function quoteColumnName(string $columnName): string
    {
        $driver = $this->getConnection()->getDriver();

        return $driver->quoteIdentifier($columnName);
    }

    /**
     * Writes a message to stdout if verbose output is on
     *
     * @param string $message The message to show
     * @return void
     */
    protected function verboseLog(string $message): void
    {
        $io = $this->getIo();
        if (
            $io === null || (
                !$this->isDryRunEnabled() &&
                $io->level() != ConsoleIo::VERBOSE
            )
        ) {
            return;
        }

        $io->out($message);
    }

    /**
     * Gets the schema table name.
     *
     * Returns the appropriate table name based on configuration:
     * - 'cake_migrations' for unified mode
     * - Phinxlog table name for backwards compatibility mode
     *
     * @return string
     */
    public function getSchemaTableName(): string
    {
        if ($this->isUsingUnifiedTable()) {
            return UnifiedMigrationsTableStorage::TABLE_NAME;
        }

        return $this->schemaTableName;
    }

    /**
     * Sets the schema table name.
     *
     * @param string $schemaTableName Schema Table Name
     * @return $this
     */
    public function setSchemaTableName(string $schemaTableName)
    {
        $this->schemaTableName = $schemaTableName;

        return $this;
    }

    /**
     * Gets the seed schema table name.
     *
     * @return string
     */
    public function getSeedSchemaTableName(): string
    {
        return $this->seedSchemaTableName;
    }

    /**
     * Sets the seed schema table name.
     *
     * @param string $seedSchemaTableName Seed Schema Table Name
     * @return $this
     */
    public function setSeedSchemaTableName(string $seedSchemaTableName)
    {
        $this->seedSchemaTableName = $seedSchemaTableName;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getColumnForType(string $columnName, string $type, array $options): Column
    {
        $column = new Column();
        $column->setName($columnName);
        $column->setType($type);
        $column->setOptions($options);

        return $column;
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
     * @throws \InvalidArgumentException
     * @return void
     */
    public function createSchemaTable(): void
    {
        $this->migrationsTable()->createTable();
    }

    /**
     * @inheritDoc
     */
    public function createSeedSchemaTable(): void
    {
        try {
            $table = new Table($this->getSeedSchemaTableName(), [], $this);
            $table->addColumn('plugin', 'string', ['limit' => 100, 'default' => null, 'null' => true])
                ->addColumn('seed_name', 'string', ['limit' => 100, 'null' => false])
                ->addColumn('executed_at', 'timestamp', ['default' => null, 'null' => true])
                ->save();
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                'There was a problem creating the seed schema table: ' . $exception->getMessage(),
                (int)$exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getAdapterType(): string
    {
        return $this->getOption('adapter');
    }

    /**
     * @inheritDoc
     */
    public function isValidColumnType(Column $column): bool
    {
        return in_array($column->getType(), $this->getColumnTypes(), true);
    }

    /**
     * @inheritDoc
     */
    public function setIo(ConsoleIo $io)
    {
        $this->io = $io;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIo(): ?ConsoleIo
    {
        return $this->io ?? null;
    }

    /**
     * Determines if instead of executing queries a dump to standard output is needed
     *
     * @return bool
     */
    public function isDryRunEnabled(): bool
    {
        return $this->getOption('dryrun') === true;
    }

    /**
     * Adds user-created tables (e.g. not phinxlog) to a cached list
     *
     * @param string $tableName The name of the table
     * @return void
     */
    protected function addCreatedTable(string $tableName): void
    {
        $tableName = $this->quoteTableName($tableName);
        if (substr_compare($tableName, 'phinxlog', -strlen('phinxlog')) !== 0) {
            $this->createdTables[] = $tableName;
        }
    }

    /**
     * Updates the name of the cached table
     *
     * @param string $tableName Original name of the table
     * @param string $newTableName New name of the table
     * @return void
     */
    protected function updateCreatedTableName(string $tableName, string $newTableName): void
    {
        $tableName = $this->quoteTableName($tableName);
        $newTableName = $this->quoteTableName($newTableName);
        $key = array_search($tableName, $this->createdTables, true);
        if ($key !== false) {
            $this->createdTables[$key] = $newTableName;
        }
    }

    /**
     * Removes table from the cached created list
     *
     * @param string $tableName The name of the table
     * @return void
     */
    protected function removeCreatedTable(string $tableName): void
    {
        $tableName = $this->quoteTableName($tableName);
        $key = array_search($tableName, $this->createdTables, true);
        if ($key !== false) {
            unset($this->createdTables[$key]);
        }
    }

    /**
     * Check if the table is in the cached list of created tables
     *
     * @param string $tableName The name of the table
     * @return bool
     */
    protected function hasCreatedTable(string $tableName): bool
    {
        $tableName = $this->quoteTableName($tableName);

        return in_array($tableName, $this->createdTables, true);
    }

    /**
     * Execute a Query object. Handles logging and dry-run modes.
     *
     * @param \Cake\Database\Query $query The query to execute
     * @return int The number of affected rows.
     */
    public function executeQuery(Query $query): int
    {
        $this->verboseLog($query->sql());

        if ($this->isDryRunEnabled()) {
            return 0;
        }
        $stmt = $query->execute();

        return $stmt->rowCount();
    }

    /**
     * @inheritDoc
     */
    public function execute(string $sql, array $params = []): int
    {
        $sql = rtrim($sql, "; \t\n\r\0\x0B") . ';';
        $this->verboseLog($sql);

        if ($this->isDryRunEnabled()) {
            return 0;
        }

        $connection = $this->getConnection();
        if (!$params) {
            $result = $connection->execute($sql);

            return $result->rowCount();
        }
        $stmt = $connection->execute($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * @inheritDoc
     */
    public function getQueryBuilder(string $type): Query
    {
        return match ($type) {
            Query::TYPE_SELECT => $this->getConnection()->selectQuery(),
            Query::TYPE_INSERT => $this->getConnection()->insertQuery(),
            Query::TYPE_UPDATE => $this->getConnection()->updateQuery(),
            Query::TYPE_DELETE => $this->getConnection()->deleteQuery(),
            default => throw new InvalidArgumentException(
                'Query type must be one of: `select`, `insert`, `update`, `delete`.',
            )
        };
    }

    /**
     * @inheritDoc
     */
    public function getSelectBuilder(): SelectQuery
    {
        return $this->getConnection()->selectQuery();
    }

    /**
     * @inheritDoc
     */
    public function getInsertBuilder(): InsertQuery
    {
        return $this->getConnection()->insertQuery();
    }

    /**
     * @inheritDoc
     */
    public function getUpdateBuilder(): UpdateQuery
    {
        return $this->getConnection()->updateQuery();
    }

    /**
     * @inheritDoc
     */
    public function getDeleteBuilder(): DeleteQuery
    {
        return $this->getConnection()->deleteQuery();
    }

    /**
     * Executes a query and returns PDOStatement.
     *
     * @param string $sql SQL
     * @return \Cake\Database\StatementInterface
     */
    public function query(string $sql, array $params = []): mixed
    {
        return $this->getConnection()->execute($sql, $params);
    }

    /**
     * @inheritDoc
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->getConnection()->execute($sql)->fetch('assoc');
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(string $sql): array
    {
        return $this->getConnection()->execute($sql)->fetchAll('assoc');
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
     * Generates the SQL for an insert.
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to insert into
     * @param array $row The row to insert
     * @param \Migrations\Db\InsertMode|null $mode Insert mode
     * @param array<string>|null $updateColumns Columns to update on upsert conflict
     * @param array<string>|null $conflictColumns Columns that define uniqueness for upsert (unused in MySQL)
     * @return string
     */
    protected function generateInsertSql(
        TableMetadata $table,
        array $row,
        ?InsertMode $mode = null,
        ?array $updateColumns = null,
        ?array $conflictColumns = null,
    ): string {
        $sql = sprintf(
            '%s INTO %s ',
            $this->getInsertPrefix($mode),
            $this->quoteTableName($table->getName()),
        );
        $columns = array_keys($row);
        $sql .= '(' . implode(', ', array_map($this->quoteColumnName(...), $columns)) . ')';

        foreach ($row as $column => $value) {
            if (is_bool($value)) {
                $row[$column] = $this->castToBool($value);
            }
        }

        $upsertClause = $this->getUpsertClause($mode, $updateColumns, $conflictColumns);

        if ($this->isDryRunEnabled()) {
            $sql .= ' VALUES (' . implode(', ', array_map($this->quoteValue(...), $row)) . ')' . $upsertClause . ';';

            return $sql;
        } else {
            $values = [];
            foreach ($row as $value) {
                $placeholder = '?';
                if ($value instanceof Literal) {
                    $placeholder = (string)$value;
                }
                $values[] = $placeholder;
            }
            $sql .= ' VALUES (' . implode(',', $values) . ')' . $upsertClause;

            return $sql;
        }
    }

    /**
     * Get the INSERT prefix based on insert mode and database type.
     *
     * @param \Migrations\Db\InsertMode|null $mode Insert mode
     * @return string
     */
    protected function getInsertPrefix(?InsertMode $mode = null): string
    {
        if ($mode === InsertMode::IGNORE) {
            return 'INSERT IGNORE';
        }

        return 'INSERT';
    }

    /**
     * Get the upsert clause for MySQL (ON DUPLICATE KEY UPDATE).
     *
     * MySQL's ON DUPLICATE KEY UPDATE applies to all unique key constraints on the table,
     * so the $conflictColumns parameter is not used. If you pass conflictColumns when using
     * MySQL, a warning will be triggered.
     *
     * @param \Migrations\Db\InsertMode|null $mode Insert mode
     * @param array<string>|null $updateColumns Columns to update on conflict
     * @param array<string>|null $conflictColumns Columns that define uniqueness (unused in MySQL)
     * @return string
     */
    protected function getUpsertClause(?InsertMode $mode, ?array $updateColumns, ?array $conflictColumns = null): string
    {
        if ($mode !== InsertMode::UPSERT || $updateColumns === null) {
            return '';
        }

        if ($conflictColumns !== null && $conflictColumns !== []) {
            trigger_error(
                'The $conflictColumns parameter is ignored by MySQL. ' .
                'MySQL\'s ON DUPLICATE KEY UPDATE applies to all unique constraints on the table.',
                E_USER_WARNING,
            );
        }

        $updates = [];
        foreach ($updateColumns as $column) {
            $quotedColumn = $this->quoteColumnName($column);
            $updates[] = $quotedColumn . ' = VALUES(' . $quotedColumn . ')';
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    }

    /**
     * Quotes a database value.
     *
     * @param mixed $value The value to quote
     * @return mixed
     */
    protected function quoteValue(mixed $value): mixed
    {
        if (is_numeric($value)) {
            return $value;
        }

        if ($value === null) {
            return 'null';
        }

        if ($value instanceof Literal) {
            return (string)$value;
        }

        if ($value instanceof DateTime) {
            $value = $value->toDateTimeString();
        } elseif ($value instanceof Date) {
            $value = $value->toDateString();
        }

        $driver = $this->getConnection()->getDriver();

        return $driver->quote($value);
    }

    /**
     * Quotes a database string.
     *
     * @param string $value The string to quote
     * @return string
     */
    protected function quoteString(string $value): string
    {
        $driver = $this->getConnection()->getDriver();

        return $driver->quote($value);
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
            }
            $this->getConnection()->execute($sql, $vals);
        }
    }

    /**
     * Generates the SQL for a bulk insert.
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to insert into
     * @param array $rows The rows to insert
     * @param \Migrations\Db\InsertMode|null $mode Insert mode
     * @param array<string>|null $updateColumns Columns to update on upsert conflict
     * @param array<string>|null $conflictColumns Columns that define uniqueness for upsert (unused in MySQL)
     * @return string
     */
    protected function generateBulkInsertSql(
        TableMetadata $table,
        array $rows,
        ?InsertMode $mode = null,
        ?array $updateColumns = null,
        ?array $conflictColumns = null,
    ): string {
        $sql = sprintf(
            '%s INTO %s ',
            $this->getInsertPrefix($mode),
            $this->quoteTableName($table->getName()),
        );
        $current = current($rows);
        $keys = array_keys($current);

        $sql .= '(' . implode(', ', array_map($this->quoteColumnName(...), $keys)) . ') VALUES ';

        $upsertClause = $this->getUpsertClause($mode, $updateColumns, $conflictColumns);

        if ($this->isDryRunEnabled()) {
            $values = array_map(function ($row) {
                return '(' . implode(', ', array_map($this->quoteValue(...), $row)) . ')';
            }, $rows);
            $sql .= implode(', ', $values) . $upsertClause . ';';

            return $sql;
        } else {
            $queries = [];
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $v) {
                    $placeholder = '?';
                    if ($v instanceof Literal) {
                        $placeholder = (string)$v;
                    }
                    $values[] = $placeholder;
                }
                $query = '(' . implode(', ', $values) . ')';
                $queries[] = $query;
            }
            $sql .= implode(',', $queries) . $upsertClause;

            return $sql;
        }
    }

    /**
     * @inheritDoc
     */
    public function getVersions(): array
    {
        $rows = $this->getVersionLog();

        return array_keys($rows);
    }

    /**
     * @inheritDoc
     */
    public function cleanupMissing(array $missingVersions): void
    {
        $storage = $this->migrationsTable();

        $storage->cleanupMissing($missingVersions);
    }

    /**
     * Get the migrations table storage implementation.
     *
     * Returns either UnifiedMigrationsTableStorage (new cake_migrations table)
     * or MigrationsTableStorage (legacy phinxlog tables) based on configuration
     * and autodetection.
     *
     * @return \Migrations\Db\Adapter\MigrationsTableStorage|\Migrations\Db\Adapter\UnifiedMigrationsTableStorage
     * @internal
     */
    protected function migrationsTable(): MigrationsTableStorage|UnifiedMigrationsTableStorage
    {
        if ($this->isUsingUnifiedTable()) {
            return new UnifiedMigrationsTableStorage(
                $this,
                $this->getOption('plugin'),
            );
        }

        return new MigrationsTableStorage(
            $this,
            $this->getSchemaTableName(),
            $this->getOption('plugin'),
        );
    }

    /**
     * Determine if using the unified cake_migrations table.
     *
     * Checks configuration and autodetects based on existing legacy tables.
     *
     * @return bool True if using unified table, false for legacy phinxlog tables
     */
    protected function isUsingUnifiedTable(): bool
    {
        $config = Configure::read('Migrations.legacyTables');

        // Explicit configuration takes precedence
        if ($config === false) {
            return true;
        }

        if ($config === true) {
            return false;
        }

        // Autodetect mode (config is null or not set)
        // Check if the main legacy phinxlog table exists
        if ($this->connection !== null) {
            $dialect = $this->connection->getDriver()->schemaDialect();
            if ($dialect->hasTable('phinxlog')) {
                return false;
            }
        }

        // No legacy phinxlog table found - use unified table
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException
     */
    public function getVersionLog(): array
    {
        switch ($this->options['version_order']) {
            case Config::VERSION_ORDER_CREATION_TIME:
                $orderBy = ['version' => 'ASC'];
                break;
            case Config::VERSION_ORDER_EXECUTION_TIME:
                $orderBy = ['start_time' => 'ASC', 'version' => 'ASC'];
                break;
            default:
                throw new RuntimeException('Invalid version_order configuration option');
        }
        $query = $this->migrationsTable()->getVersions($orderBy);

        // This will throw an exception if doing a --dry-run without any migrations as phinxlog
        // does not exist, so in that case, we can just expect to trivially return empty set
        try {
            $rows = $query->execute()->fetchAll('assoc');
        } catch (PDOException $e) {
            if (!$this->isDryRunEnabled()) {
                throw $e;
            }
            $rows = [];
        }

        $result = [];
        foreach ($rows as $version) {
            $result[(int)$version['version']] = $version;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function migrated(MigrationInterface $migration, string $direction, string $startTime, string $endTime): AdapterInterface
    {
        if (strcasecmp($direction, MigrationInterface::UP) === 0) {
            $this->migrationsTable()->recordUp($migration, $startTime, $endTime);
        } else {
            // down
            $this->migrationsTable()->recordDown($migration);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function toggleBreakpoint(MigrationInterface $migration): AdapterInterface
    {
        $this->migrationsTable()->toggleBreakpoint($migration);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resetAllBreakpoints(): int
    {
        return $this->migrationsTable()->resetAllBreakpoints();
    }

    /**
     * @inheritDoc
     */
    public function setBreakpoint(MigrationInterface $migration): AdapterInterface
    {
        $this->markBreakpoint($migration, true);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function unsetBreakpoint(MigrationInterface $migration): AdapterInterface
    {
        $this->markBreakpoint($migration, false);

        return $this;
    }

    /**
     * Mark a migration breakpoint.
     *
     * @param \Migrations\MigrationInterface $migration The migration target for the breakpoint
     * @param bool $state The required state of the breakpoint
     * @return \Migrations\Db\Adapter\AdapterInterface
     */
    protected function markBreakpoint(MigrationInterface $migration, bool $state): AdapterInterface
    {
        $this->migrationsTable()->markBreakpoint($migration, $state);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getSeedLog(): array
    {
        $query = $this->getSelectBuilder();
        $query->select('*')
            ->from($this->getSeedSchemaTableName())
            ->orderBy(['executed_at' => 'ASC', 'id' => 'ASC']);

        try {
            $rows = $query->execute()->fetchAll('assoc');
        } catch (PDOException $e) {
            if (!$this->isDryRunEnabled()) {
                throw $e;
            }
            $rows = [];
        }

        return $rows;
    }

    /**
     * @inheritDoc
     */
    public function seedExecuted(SeedInterface $seed, string $executedTime): AdapterInterface
    {
        $plugin = null;
        $className = get_class($seed);

        if (str_contains($className, '\\')) {
            $parts = explode('\\', $className);
            $appNamespace = Configure::read('App.namespace', 'App');
            if (count($parts) > 1 && $parts[0] !== $appNamespace) {
                $plugin = $parts[0];
            }
        }

        $seedName = substr($seed->getName(), 0, 100);

        $query = $this->getInsertBuilder();
        $query->insert(['plugin', 'seed_name', 'executed_at'])
            ->into($this->getSeedSchemaTableName())
            ->values([
                'plugin' => $plugin,
                'seed_name' => $seedName,
                'executed_at' => $executedTime,
            ]);
        $this->executeQuery($query);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function removeSeedFromLog(SeedInterface $seed): AdapterInterface
    {
        $plugin = null;
        $className = get_class($seed);

        if (str_contains($className, '\\')) {
            $parts = explode('\\', $className);
            $appNamespace = Configure::read('App.namespace', 'App');
            if (count($parts) > 1 && $parts[0] !== $appNamespace) {
                $plugin = $parts[0];
            }
        }

        $seedName = $seed->getName();

        $query = $this->getDeleteBuilder();
        $query->delete()
            ->from($this->getSeedSchemaTableName())
            ->where([
                'seed_name' => $seedName,
                'plugin IS' => $plugin,
            ]);
        $this->executeQuery($query);

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function createSchema(string $schemaName = 'public'): void
    {
        throw new BadMethodCallException('Creating a schema is not supported');
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function dropSchema(string $schemaName): void
    {
        throw new BadMethodCallException('Dropping a schema is not supported');
    }

    /**
     * @inheritDoc
     */
    public function getColumnTypes(): array
    {
        return [
            'string',
            'char',
            'text',
            'tinyinteger',
            'smallinteger',
            'integer',
            'biginteger',
            'bit',
            'float',
            'decimal',
            'double',
            'datetime',
            'datetimefractional',
            'timestamp',
            'timestampfractional',
            'timestamptimezone',
            'time',
            'date',
            'blob',
            'binary',
            'varbinary',
            'boolean',
            'uuid',
            // Geospatial data types
            'geometry',
            'point',
            'linestring',
            'polygon',
        ];
    }

    /**
     * @inheritDoc
     */
    public function castToBool($value): mixed
    {
        return (bool)$value ? 1 : 0;
    }

    /**
     * Get the definition for a `DEFAULT` statement.
     *
     * @param mixed $default Default value
     * @param string|null $columnType column type added
     * @return string
     */
    protected function getDefaultValueDefinition(mixed $default, ?string $columnType = null): string
    {
        // SQL functions mapped to their valid column types (ordered longest-first to avoid prefix conflicts)
        $sqlFunctionTypes = [
            'CURRENT_TIMESTAMP' => [static::TYPE_DATETIME, static::TYPE_TIMESTAMP, static::TYPE_TIME, static::TYPE_DATE],
            'CURRENT_DATE' => [static::TYPE_DATE],
            'CURRENT_TIME' => [static::TYPE_TIME],
        ];

        if ($default instanceof Literal) {
            $default = (string)$default;
        } elseif (is_string($default) && $columnType !== null) {
            $matched = false;
            foreach ($sqlFunctionTypes as $function => $validTypes) {
                // Match function name at start, followed by end of string or opening parenthesis
                $len = strlen($function);
                if (
                    stripos($default, $function) === 0 &&
                    (strlen($default) === $len || $default[$len] === '(') &&
                    in_array($columnType, $validTypes, true)
                ) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $default = $this->quoteString($default);
            }
        } elseif (is_string($default)) {
            $default = $this->quoteString($default);
        } elseif (is_bool($default)) {
            $default = $this->castToBool($default);
        } elseif ($default !== null && $columnType === static::TYPE_BOOLEAN) {
            $default = $this->castToBool((bool)$default);
        }

        return isset($default) ? " DEFAULT $default" : '';
    }

    /**
     * Executes all the ALTER TABLE instructions passed for the given table
     *
     * @param string $tableName The table name to use in the ALTER statement
     * @param \Migrations\Db\AlterInstructions $instructions The object containing the alter sequence
     * @return void
     */
    protected function executeAlterSteps(string $tableName, AlterInstructions $instructions): void
    {
        $alter = sprintf('ALTER TABLE %s %%s', $this->quoteTableName($tableName));
        $instructions->execute($alter, [$this, 'execute']);
    }

    /**
     * @inheritDoc
     */
    public function addColumn(TableMetadata $table, Column $column): void
    {
        $instructions = $this->getAddColumnInstructions($table, $column);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to add the specified column to a database table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table Table
     * @param \Migrations\Db\Table\Column $column Column
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getAddColumnInstructions(TableMetadata $table, Column $column): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function renameColumn(string $tableName, string $columnName, string $newColumnName): void
    {
        $instructions = $this->getRenameColumnInstructions($tableName, $columnName, $newColumnName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to rename the specified column.
     *
     * @param string $tableName Table name
     * @param string $columnName Column Name
     * @param string $newColumnName New Column Name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getRenameColumnInstructions(string $tableName, string $columnName, string $newColumnName): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function changeColumn(string $tableName, string $columnName, Column $newColumn): void
    {
        $instructions = $this->getChangeColumnInstructions($tableName, $columnName, $newColumn);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to change a table column type.
     *
     * @param string $tableName Table name
     * @param string $columnName Column Name
     * @param \Migrations\Db\Table\Column $newColumn New Column
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getChangeColumnInstructions(string $tableName, string $columnName, Column $newColumn): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function dropColumn(string $tableName, string $columnName): void
    {
        $instructions = $this->getDropColumnInstructions($tableName, $columnName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified column.
     *
     * @param string $tableName Table name
     * @param string $columnName Column Name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropColumnInstructions(string $tableName, string $columnName): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function addIndex(TableMetadata $table, Index $index): void
    {
        $instructions = $this->getAddIndexInstructions($table, $index);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to add the specified index to a database table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table Table
     * @param \Migrations\Db\Table\Index $index Index
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getAddIndexInstructions(TableMetadata $table, Index $index): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function dropIndex(string $tableName, string|array $columns): void
    {
        $instructions = $this->getDropIndexByColumnsInstructions($tableName, $columns);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified index from a database table.
     *
     * @param string $tableName The name of the table where the index is
     * @param string|string[] $columns Column(s)
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropIndexByColumnsInstructions(string $tableName, string|array $columns): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function dropIndexByName(string $tableName, string $indexName): void
    {
        $instructions = $this->getDropIndexByNameInstructions($tableName, $indexName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the index specified by name from a database table.
     *
     * @param string $tableName The table name whe the index is
     * @param string $indexName The name of the index
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropIndexByNameInstructions(string $tableName, string $indexName): AlterInstructions;

    /**
     * @inheritDoc
     */
    public function hasIndex(string $tableName, string|array $columns): bool
    {
        $dialect = $this->getSchemaDialect();
        $columns = is_array($columns) ? $columns : [$columns];

        return $dialect->hasIndex($tableName, $columns);
    }

    /**
     * @inheritDoc
     */
    public function hasIndexByName(string $tableName, string $indexName): bool
    {
        $dialect = $this->getSchemaDialect();

        return $dialect->hasIndex($tableName, [], $indexName);
    }

    /**
     * @inheritdoc
     */
    public function addForeignKey(TableMetadata $table, ForeignKey $foreignKey): void
    {
        $instructions = $this->getAddForeignKeyInstructions($table, $foreignKey);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to adds the specified foreign key to a database table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to add the constraint to
     * @param \Migrations\Db\Table\ForeignKey $foreignKey The foreign key to add
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getAddForeignKeyInstructions(TableMetadata $table, ForeignKey $foreignKey): AlterInstructions;

    /**
     * @inheritDoc
     */
    public function dropForeignKey(string $tableName, array $columns, ?string $constraint = null): void
    {
        if ($constraint) {
            $instructions = $this->getDropForeignKeyInstructions($tableName, $constraint);
        } else {
            $instructions = $this->getDropForeignKeyByColumnsInstructions($tableName, $columns);
        }

        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified foreign key from a database table.
     *
     * @param string $tableName The table where the foreign key constraint is
     * @param string $constraint Constraint name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropForeignKeyInstructions(string $tableName, string $constraint): AlterInstructions;

    /**
     * Returns the instructions to drop the specified foreign key from a database table.
     *
     * @param string $tableName The table where the foreign key constraint is
     * @param string[] $columns The list of column names
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropForeignKeyByColumnsInstructions(string $tableName, array $columns): AlterInstructions;

    /**
     * @inheritDoc
     */
    public function hasForeignKey(string $tableName, $columns, ?string $constraint = null): bool
    {
        $dialect = $this->getSchemaDialect();
        $columns = is_array($columns) ? $columns : [$columns];

        return $dialect->hasForeignKey($tableName, $columns, $constraint);
    }

    /**
     * @inheritDoc
     */
    public function hasCheckConstraint(string $tableName, string $constraintName): bool
    {
        $constraints = $this->getCheckConstraints($tableName);

        foreach ($constraints as $constraint) {
            if ($constraint['name'] === $constraintName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get check constraints for a table.
     *
     * @param string $tableName Table name
     * @return array
     */
    abstract protected function getCheckConstraints(string $tableName): array;

    /**
     * @inheritDoc
     */
    public function addCheckConstraint(TableMetadata $table, CheckConstraint $checkConstraint): void
    {
        $instructions = $this->getAddCheckConstraintInstructions($table, $checkConstraint);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to add the specified check constraint to a database table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to add the constraint to
     * @param \Migrations\Db\Table\CheckConstraint $checkConstraint The check constraint
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getAddCheckConstraintInstructions(TableMetadata $table, CheckConstraint $checkConstraint): AlterInstructions;

    /**
     * @inheritDoc
     */
    public function dropCheckConstraint(string $tableName, string $constraintName): void
    {
        $instructions = $this->getDropCheckConstraintInstructions($tableName, $constraintName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified check constraint from a database table.
     *
     * @param string $tableName The table name
     * @param string $constraintName The constraint name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropCheckConstraintInstructions(string $tableName, string $constraintName): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function dropTable(string $tableName): void
    {
        $instructions = $this->getDropTableInstructions($tableName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to drop the specified database table.
     *
     * @param string $tableName Table name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getDropTableInstructions(string $tableName): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function renameTable(string $tableName, string $newName): void
    {
        $instructions = $this->getRenameTableInstructions($tableName, $newName);
        $this->executeAlterSteps($tableName, $instructions);
    }

    /**
     * Returns the instructions to rename the specified database table.
     *
     * @param string $tableName Table name
     * @param string $newTableName New Name
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getRenameTableInstructions(string $tableName, string $newTableName): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function changePrimaryKey(TableMetadata $table, string|array|null $newColumns): void
    {
        $instructions = $this->getChangePrimaryKeyInstructions($table, $newColumns);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instructions to change the primary key for the specified database table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table Table
     * @param string|string[]|null $newColumns Column name(s) to belong to the primary key, or null to drop the key
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getChangePrimaryKeyInstructions(TableMetadata $table, string|array|null $newColumns): AlterInstructions;

    /**
     * @inheritdoc
     */
    public function changeComment(TableMetadata $table, $newComment): void
    {
        $instructions = $this->getChangeCommentInstructions($table, $newComment);
        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Returns the instruction to change the comment for the specified database table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table Table
     * @param string|null $newComment New comment string, or null to drop the comment
     * @return \Migrations\Db\AlterInstructions
     */
    abstract protected function getChangeCommentInstructions(TableMetadata $table, ?string $newComment): AlterInstructions;

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public function executeActions(TableMetadata $table, array $actions): void
    {
        $instructions = new AlterInstructions();

        // Collect partition actions separately as they need special batching
        /** @var \Migrations\Db\Table\PartitionDefinition[] $addPartitions */
        $addPartitions = [];
        /** @var string[] $dropPartitions */
        $dropPartitions = [];

        foreach ($actions as $action) {
            switch (true) {
                case $action instanceof AddColumn:
                    /** @var \Migrations\Db\Action\AddColumn $action */
                    $instructions->merge($this->getAddColumnInstructions($table, $action->getColumn()));
                    break;

                case $action instanceof AddIndex:
                    /** @var \Migrations\Db\Action\AddIndex $action */
                    $instructions->merge($this->getAddIndexInstructions($table, $action->getIndex()));
                    break;

                case $action instanceof AddForeignKey:
                    /** @var \Migrations\Db\Action\AddForeignKey $action */
                    $instructions->merge($this->getAddForeignKeyInstructions($table, $action->getForeignKey()));
                    break;

                case $action instanceof ChangeColumn:
                    /** @var \Migrations\Db\Action\ChangeColumn $action */
                    $instructions->merge($this->getChangeColumnInstructions(
                        $table->getName(),
                        $action->getColumnName(),
                        $action->getColumn(),
                    ));
                    break;

                case $action instanceof DropForeignKey && !$action->getForeignKey()->getName():
                    /** @var \Migrations\Db\Action\DropForeignKey $action */
                    $instructions->merge($this->getDropForeignKeyByColumnsInstructions(
                        $table->getName(),
                        $action->getForeignKey()->getColumns(),
                    ));
                    break;

                case $action instanceof DropForeignKey && $action->getForeignKey()->getName():
                    /** @var \Migrations\Db\Action\DropForeignKey $action */
                    $instructions->merge($this->getDropForeignKeyInstructions(
                        $table->getName(),
                        (string)$action->getForeignKey()->getName(),
                    ));
                    break;

                case $action instanceof DropIndex && $action->getIndex()->getName():
                    /** @var \Migrations\Db\Action\DropIndex $action */
                    $instructions->merge($this->getDropIndexByNameInstructions(
                        $table->getName(),
                        (string)$action->getIndex()->getName(),
                    ));
                    break;

                case $action instanceof DropIndex && !$action->getIndex()->getName():
                    /** @var \Migrations\Db\Action\DropIndex $action */
                    $instructions->merge($this->getDropIndexByColumnsInstructions(
                        $table->getName(),
                        (array)$action->getIndex()->getColumns(),
                    ));
                    break;

                case $action instanceof DropTable:
                    /** @var \Migrations\Db\Action\DropTable $action */
                    $instructions->merge($this->getDropTableInstructions(
                        $table->getName(),
                    ));
                    break;

                case $action instanceof RemoveColumn:
                    /** @var \Migrations\Db\Action\RemoveColumn $action */
                    $instructions->merge($this->getDropColumnInstructions(
                        $table->getName(),
                        (string)$action->getColumn()->getName(),
                    ));
                    break;

                case $action instanceof RenameColumn:
                    /** @var \Migrations\Db\Action\RenameColumn $action */
                    $instructions->merge($this->getRenameColumnInstructions(
                        $table->getName(),
                        (string)$action->getColumn()->getName(),
                        $action->getNewName(),
                    ));
                    break;

                case $action instanceof RenameTable:
                    /** @var \Migrations\Db\Action\RenameTable $action */
                    $instructions->merge($this->getRenameTableInstructions(
                        $table->getName(),
                        $action->getNewName(),
                    ));
                    break;

                case $action instanceof ChangePrimaryKey:
                    /** @var \Migrations\Db\Action\ChangePrimaryKey $action */
                    $instructions->merge($this->getChangePrimaryKeyInstructions(
                        $table,
                        $action->getNewColumns(),
                    ));
                    break;

                case $action instanceof ChangeComment:
                    /** @var \Migrations\Db\Action\ChangeComment $action */
                    $instructions->merge($this->getChangeCommentInstructions(
                        $table,
                        $action->getNewComment(),
                    ));
                    break;

                case $action instanceof AddPartition:
                    /** @var \Migrations\Db\Action\AddPartition $action */
                    $addPartitions[] = $action->getPartition();
                    break;

                case $action instanceof DropPartition:
                    /** @var \Migrations\Db\Action\DropPartition $action */
                    $dropPartitions[] = $action->getPartitionName();
                    break;

                case $action instanceof SetPartitioning:
                    /** @var \Migrations\Db\Action\SetPartitioning $action */
                    $instructions->merge($this->getSetPartitioningInstructions(
                        $table,
                        $action->getPartition(),
                    ));
                    break;

                default:
                    throw new InvalidArgumentException(
                        sprintf("Don't know how to execute action `%s`", get_class($action)),
                    );
            }
        }

        // Handle batched partition operations
        if ($addPartitions) {
            $instructions->merge($this->getAddPartitionsInstructions($table, $addPartitions));
        }
        if ($dropPartitions) {
            $instructions->merge($this->getDropPartitionsInstructions($table->getName(), $dropPartitions));
        }

        $this->executeAlterSteps($table->getName(), $instructions);
    }

    /**
     * Get instructions for adding multiple partitions to an existing table.
     *
     * This method handles batching multiple partition additions into a single
     * ALTER TABLE statement where supported by the database.
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table
     * @param array<\Migrations\Db\Table\PartitionDefinition> $partitions The partitions to add
     * @return \Migrations\Db\AlterInstructions
     */
    protected function getAddPartitionsInstructions(TableMetadata $table, array $partitions): AlterInstructions
    {
        throw new RuntimeException('Table partitioning is not supported by this adapter');
    }

    /**
     * Get instructions for dropping multiple partitions from an existing table.
     *
     * This method handles batching multiple partition drops into a single
     * ALTER TABLE statement where supported by the database.
     *
     * @param string $tableName The table name
     * @param array<string> $partitionNames The partition names to drop
     * @return \Migrations\Db\AlterInstructions
     */
    protected function getDropPartitionsInstructions(string $tableName, array $partitionNames): AlterInstructions
    {
        throw new RuntimeException('Table partitioning is not supported by this adapter');
    }

    /**
     * Get instructions for adding partitioning to an existing table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table
     * @param \Migrations\Db\Table\Partition $partition The partition configuration
     * @throws \RuntimeException If partitioning is not supported
     * @return \Migrations\Db\AlterInstructions
     */
    protected function getSetPartitioningInstructions(TableMetadata $table, Partition $partition): AlterInstructions
    {
        throw new RuntimeException('Adding partitioning to existing tables is not supported by this adapter');
    }
}
