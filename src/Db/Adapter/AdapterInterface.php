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
use Cake\Database\Schema\TableSchemaInterface;
use Migrations\Db\InsertMode;
use Migrations\Db\Table\CheckConstraint;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\TableMetadata;
use Migrations\MigrationInterface;
use Migrations\SeedInterface;

/**
 * Adapter Interface.
 */
interface AdapterInterface
{
    public const TYPE_STRING = TableSchemaInterface::TYPE_STRING;
    public const TYPE_CHAR = TableSchemaInterface::TYPE_CHAR;
    public const TYPE_TEXT = TableSchemaInterface::TYPE_TEXT;
    public const TYPE_INTEGER = TableSchemaInterface::TYPE_INTEGER;
    public const TYPE_TINYINTEGER = TableSchemaInterface::TYPE_TINYINTEGER;
    public const TYPE_SMALLINTEGER = TableSchemaInterface::TYPE_SMALLINTEGER;
    public const TYPE_BIGINTEGER = TableSchemaInterface::TYPE_BIGINTEGER;
    public const TYPE_FLOAT = TableSchemaInterface::TYPE_FLOAT;
    public const TYPE_DECIMAL = TableSchemaInterface::TYPE_DECIMAL;
    public const TYPE_DATETIME = TableSchemaInterface::TYPE_DATETIME;
    public const TYPE_TIMESTAMP = TableSchemaInterface::TYPE_TIMESTAMP;
    public const TYPE_TIME = TableSchemaInterface::TYPE_TIME;
    public const TYPE_DATE = TableSchemaInterface::TYPE_DATE;
    public const TYPE_BINARY = TableSchemaInterface::TYPE_BINARY;
    public const TYPE_BINARY_UUID = TableSchemaInterface::TYPE_BINARY_UUID;
    public const TYPE_BOOLEAN = TableSchemaInterface::TYPE_BOOLEAN;
    public const TYPE_JSON = TableSchemaInterface::TYPE_JSON;
    public const TYPE_UUID = TableSchemaInterface::TYPE_UUID;
    public const TYPE_NATIVE_UUID = TableSchemaInterface::TYPE_NATIVE_UUID;

    // Geospatial database types
    public const TYPE_GEOMETRY = TableSchemaInterface::TYPE_GEOMETRY;
    public const TYPE_POINT = TableSchemaInterface::TYPE_POINT;
    public const TYPE_LINESTRING = TableSchemaInterface::TYPE_LINESTRING;
    public const TYPE_POLYGON = TableSchemaInterface::TYPE_POLYGON;

    public const TYPES_GEOSPATIAL = [
        self::TYPE_GEOMETRY,
        self::TYPE_POINT,
        self::TYPE_LINESTRING,
        self::TYPE_POLYGON,
    ];

    // only for mysql so far
    public const TYPE_YEAR = TableSchemaInterface::TYPE_YEAR;
    public const TYPE_BIT = TableSchemaInterface::TYPE_BIT;

    // only for postgresql so far
    public const TYPE_CIDR = TableSchemaInterface::TYPE_CIDR;
    public const TYPE_INET = TableSchemaInterface::TYPE_INET;
    public const TYPE_MACADDR = TableSchemaInterface::TYPE_MACADDR;
    public const TYPE_INTERVAL = TableSchemaInterface::TYPE_INTERVAL;

    /**
     * @deprecated 5.0.0 Use TYPE_STRING instead.
     */
    public const PHINX_TYPE_STRING = self::TYPE_STRING;
    /**
     * @deprecated 5.0.0 Use TYPE_CHAR instead.
     */
    public const PHINX_TYPE_CHAR = self::TYPE_CHAR;
    /**
     * @deprecated 5.0.0 Use TYPE_TEXT instead.
     */
    public const PHINX_TYPE_TEXT = self::TYPE_TEXT;
    /**
     * @deprecated 5.0.0 Use TYPE_INTEGER instead.
     */
    public const PHINX_TYPE_INTEGER = self::TYPE_INTEGER;
    /**
     * @deprecated 5.0.0 Use TYPE_TINYINTEGER instead.
     */
    public const PHINX_TYPE_TINY_INTEGER = self::TYPE_TINYINTEGER;
    /**
     * @deprecated 5.0.0 Use TYPE_SMALLINTEGER instead.
     */
    public const PHINX_TYPE_SMALL_INTEGER = self::TYPE_SMALLINTEGER;
    /**
     * @deprecated 5.0.0 Use TYPE_BIGINTEGER instead.
     */
    public const PHINX_TYPE_BIG_INTEGER = self::TYPE_BIGINTEGER;
    /**
     * @deprecated 5.0.0 Use TYPE_FLOAT instead.
     */
    public const PHINX_TYPE_FLOAT = self::TYPE_FLOAT;
    /**
     * @deprecated 5.0.0 Use TYPE_DECIMAL instead.
     */
    public const PHINX_TYPE_DECIMAL = self::TYPE_DECIMAL;
    /**
     * @deprecated 5.0.0 Use TYPE_DATETIME instead.
     */
    public const PHINX_TYPE_DATETIME = self::TYPE_DATETIME;
    /**
     * @deprecated 5.0.0 Use TYPE_TIMESTAMP instead.
     */
    public const PHINX_TYPE_TIMESTAMP = self::TYPE_TIMESTAMP;
    /**
     * @deprecated 5.0.0 Use TYPE_TIME instead.
     */
    public const PHINX_TYPE_TIME = self::TYPE_TIME;
    /**
     * @deprecated 5.0.0 Use TYPE_DATE instead.
     */
    public const PHINX_TYPE_DATE = self::TYPE_DATE;
    /**
     * @deprecated 5.0.0 Use TYPE_BINARY instead.
     */
    public const PHINX_TYPE_BINARY = self::TYPE_BINARY;
    /**
     * @deprecated 5.0.0 Use TYPE_BINARY_UUID instead.
     */
    public const PHINX_TYPE_BINARYUUID = self::TYPE_BINARY_UUID;
    /**
     * @deprecated 5.0.0 Use TYPE_BOOLEAN instead.
     */
    public const PHINX_TYPE_BOOLEAN = self::TYPE_BOOLEAN;
    /**
     * @deprecated 5.0.0 Use TYPE_JSON instead.
     */
    public const PHINX_TYPE_JSON = self::TYPE_JSON;
    /**
     * @deprecated 5.0.0 Use TableSchemaInterface::TYPE_JSON instead.
     */
    public const PHINX_TYPE_JSONB = 'jsonb';
    /**
     * @deprecated 5.0.0 Use TYPE_UUID instead.
     */
    public const PHINX_TYPE_UUID = self::TYPE_UUID;
    /**
     * @deprecated 5.0.0 Use TYPE_NATIVE_UUID instead.
     */
    public const PHINX_TYPE_NATIVEUUID = self::TYPE_NATIVE_UUID;

    /**
     * @deprecated 5.0.0 Use TYPE_GEOMETRY instead.
     */
    public const PHINX_TYPE_GEOMETRY = self::TYPE_GEOMETRY;
    /**
     * @deprecated 5.0.0 Use TYPE_POINT instead.
     */
    public const PHINX_TYPE_POINT = self::TYPE_POINT;
    /**
     * @deprecated 5.0.0 Use TYPE_LINESTRING instead.
     */
    public const PHINX_TYPE_LINESTRING = self::TYPE_LINESTRING;
    /**
     * @deprecated 5.0.0 Use TYPE_POLYGON instead.
     */
    public const PHINX_TYPE_POLYGON = self::TYPE_POLYGON;

    /**
     * @deprecated 5.0.0 Use TYPES_GEOSPATIAL instead.
     */
    public const PHINX_TYPES_GEOSPATIAL = [
        self::TYPE_GEOMETRY,
        self::TYPE_POINT,
        self::TYPE_LINESTRING,
        self::TYPE_POLYGON,
    ];

    /**
     * @deprecated 5.0.0 Use TYPE_YEAR instead.
     */
    public const PHINX_TYPE_YEAR = self::TYPE_YEAR;

    /**
     * @deprecated 5.0.0 Use TYPE_CIDR instead.
     */
    public const PHINX_TYPE_CIDR = self::TYPE_CIDR;
    /**
     * @deprecated 5.0.0 Use TYPE_INET instead.
     */
    public const PHINX_TYPE_INET = self::TYPE_INET;
    /**
     * @deprecated 5.0.0 Use TYPE_MACADDR instead.
     */
    public const PHINX_TYPE_MACADDR = self::TYPE_MACADDR;
    /**
     * @deprecated 5.0.0 Use TYPE_INTERVAL instead.
     */
    public const PHINX_TYPE_INTERVAL = self::TYPE_INTERVAL;

    /**
     * Get all migrated version numbers.
     *
     * @return array<int>
     */
    public function getVersions(): array;

    /**
     * Get all migration log entries, indexed by version creation time and sorted ascendingly by the configuration's
     * version order option
     *
     * @return array<int, mixed>
     */
    public function getVersionLog(): array;

    /**
     * Set adapter configuration options.
     *
     * @param array<string, mixed> $options Options
     * @return $this
     */
    public function setOptions(array $options);

    /**
     * Get all adapter options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    /**
     * Check if an option has been set.
     *
     * @param string $name Name
     * @return bool
     */
    public function hasOption(string $name): bool;

    /**
     * Get a single adapter option, or null if the option does not exist.
     *
     * @param string $name Name
     * @return mixed
     */
    public function getOption(string $name): mixed;

    /**
     * Returns a new Migrations\Db\Table\Column using the existent data domain.
     *
     * @param string $columnName The desired column name
     * @param string $type The type for the column. Can be a data domain type.
     * @param array<string, mixed> $options Options array
     * @return \Migrations\Db\Table\Column
     */
    public function getColumnForType(string $columnName, string $type, array $options): Column;

    /**
     * Records a migration being run.
     *
     * @param \Migrations\MigrationInterface $migration Migration
     * @param string $direction Direction
     * @param string $startTime Start Time
     * @param string $endTime End Time
     * @return $this
     */
    public function migrated(MigrationInterface $migration, string $direction, string $startTime, string $endTime);

    /**
     * Toggle a migration breakpoint.
     *
     * @param \Migrations\MigrationInterface $migration Migration
     * @return $this
     */
    public function toggleBreakpoint(MigrationInterface $migration);

    /**
     * Reset all migration breakpoints.
     *
     * @return int The number of breakpoints reset
     */
    public function resetAllBreakpoints(): int;

    /**
     * Set a migration breakpoint.
     *
     * @param \Migrations\MigrationInterface $migration The migration target for the breakpoint set
     * @return $this
     */
    public function setBreakpoint(MigrationInterface $migration);

    /**
     * Unset a migration breakpoint.
     *
     * @param \Migrations\MigrationInterface $migration The migration target for the breakpoint unset
     * @return $this
     */
    public function unsetBreakpoint(MigrationInterface $migration);

    /**
     * Creates the schema table.
     *
     * @return void
     */
    public function createSchemaTable(): void;

    /**
     * Creates the seed schema table.
     *
     * @return void
     */
    public function createSeedSchemaTable(): void;

    /**
     * Gets the seed schema table name.
     *
     * @return string
     */
    public function getSeedSchemaTableName(): string;

    /**
     * Get all seed log entries.
     *
     * @return array<int, mixed>
     */
    public function getSeedLog(): array;

    /**
     * Records a seed being executed.
     *
     * @param \Migrations\SeedInterface $seed Seed
     * @param string $executedTime Executed Time
     * @return $this
     */
    public function seedExecuted(SeedInterface $seed, string $executedTime);

    /**
     * Removes a seed from the log.
     *
     * @param \Migrations\SeedInterface $seed Seed
     * @return $this
     */
    public function removeSeedFromLog(SeedInterface $seed);

    /**
     * Returns the adapter type.
     *
     * @return string
     */
    public function getAdapterType(): string;

    /**
     * Initializes the database connection.
     *
     * @throws \RuntimeException When the requested database driver is not installed.
     * @return void
     */
    public function connect(): void;

    /**
     * Closes the database connection.
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Does the adapter support transactions?
     *
     * @return bool
     */
    public function hasTransactions(): bool;

    /**
     * Begin a transaction.
     *
     * @return void
     */
    public function beginTransaction(): void;

    /**
     * Commit a transaction.
     *
     * @return void
     */
    public function commitTransaction(): void;

    /**
     * Rollback a transaction.
     *
     * @return void
     */
    public function rollbackTransaction(): void;

    /**
     * Executes a SQL statement and returns the number of affected rows.
     *
     * @param string $sql SQL
     * @param array $params parameters to use for prepared query
     * @return int
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * Executes a list of migration actions for the given table
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to execute the actions for
     * @param \Migrations\Db\Action\Action[] $actions The table to execute the actions for
     * @return void
     */
    public function executeActions(TableMetadata $table, array $actions): void;

    /**
     * Returns a new Query object
     *
     * @deprecated 4.x Use getSelectBuilder, getInsertBuilder, getUpdateBuilder, getDeleteBuilder instead.
     * @return \Cake\Database\Query
     */
    public function getQueryBuilder(string $type): Query;

    /**
     * Return a new SelectQuery object
     *
     * @return \Cake\Database\Query\SelectQuery
     */
    public function getSelectBuilder(): SelectQuery;

    /**
     * Return a new InsertQuery object
     *
     * @return \Cake\Database\Query\InsertQuery
     */
    public function getInsertBuilder(): InsertQuery;

    /**
     * Return a new UpdateQuery object
     *
     * @return \Cake\Database\Query\UpdateQuery
     */
    public function getUpdateBuilder(): UpdateQuery;

    /**
     * Return a new DeleteQuery object
     *
     * @return \Cake\Database\Query\DeleteQuery
     */
    public function getDeleteBuilder(): DeleteQuery;

    /**
     * Executes a SQL statement.
     *
     * The return type depends on the underlying adapter being used.
     *
     * @param string $sql SQL
     * @param array $params parameters to use for prepared query
     * @return mixed
     */
    public function query(string $sql, array $params = []): mixed;

    /**
     * Executes a query and returns only one row as an array.
     *
     * @param string $sql SQL
     * @return array|false
     */
    public function fetchRow(string $sql): array|false;

    /**
     * Executes a query and returns an array of rows.
     *
     * @param string $sql SQL
     * @return array
     */
    public function fetchAll(string $sql): array;

    /**
     * Inserts data into a table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table Table where to insert data
     * @param array $row Row
     * @param \Migrations\Db\InsertMode|null $mode Insert mode
     * @param array<string>|null $updateColumns Columns to update on upsert conflict
     * @param array<string>|null $conflictColumns Columns that define uniqueness for upsert
     * @return void
     */
    public function insert(
        TableMetadata $table,
        array $row,
        ?InsertMode $mode = null,
        ?array $updateColumns = null,
        ?array $conflictColumns = null,
    ): void;

    /**
     * Inserts data into a table in a bulk.
     *
     * @param \Migrations\Db\Table\TableMetadata $table Table where to insert data
     * @param array $rows Rows
     * @param \Migrations\Db\InsertMode|null $mode Insert mode
     * @param array<string>|null $updateColumns Columns to update on upsert conflict
     * @param array<string>|null $conflictColumns Columns that define uniqueness for upsert
     * @return void
     */
    public function bulkinsert(
        TableMetadata $table,
        array $rows,
        ?InsertMode $mode = null,
        ?array $updateColumns = null,
        ?array $conflictColumns = null,
    ): void;

    /**
     * Quotes a table name for use in a query.
     *
     * @param string $tableName Table name
     * @return string
     */
    public function quoteTableName(string $tableName): string;

    /**
     * Quotes a column name for use in a query.
     *
     * @param string $columnName Table name
     * @return string
     */
    public function quoteColumnName(string $columnName): string;

    /**
     * Checks to see if a table exists.
     *
     * @param string $tableName Table name
     * @return bool
     */
    public function hasTable(string $tableName): bool;

    /**
     * Creates the specified database table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table Table
     * @param \Migrations\Db\Table\Column[] $columns List of columns in the table
     * @param \Migrations\Db\Table\Index[] $indexes List of indexes for the table
     * @return void
     */
    public function createTable(TableMetadata $table, array $columns = [], array $indexes = []): void;

    /**
     * Truncates the specified table
     *
     * @param string $tableName Table name
     * @return void
     */
    public function truncateTable(string $tableName): void;

    /**
     * Returns table columns
     *
     * @param string $tableName Table name
     * @return \Migrations\Db\Table\Column[]
     */
    public function getColumns(string $tableName): array;

    /**
     * Checks to see if a column exists.
     *
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return bool
     */
    public function hasColumn(string $tableName, string $columnName): bool;

    /**
     * Checks to see if an index exists.
     *
     * @param string $tableName Table name
     * @param string|string[] $columns Column(s)
     * @return bool
     */
    public function hasIndex(string $tableName, string|array $columns): bool;

    /**
     * Checks to see if an index specified by name exists.
     *
     * @param string $tableName Table name
     * @param string $indexName Index name
     * @return bool
     */
    public function hasIndexByName(string $tableName, string $indexName): bool;

    /**
     * Checks to see if the specified primary key exists.
     *
     * @param string $tableName Table name
     * @param string|string[] $columns Column(s)
     * @param string|null $constraint Constraint name
     * @return bool
     */
    public function hasPrimaryKey(string $tableName, string|array $columns, ?string $constraint = null): bool;

    /**
     * Checks to see if a foreign key exists.
     *
     * @param string $tableName Table name
     * @param string|string[] $columns Column(s)
     * @param string|null $constraint Constraint name
     * @return bool
     */
    public function hasForeignKey(string $tableName, string|array $columns, ?string $constraint = null): bool;

    /**
     * Checks to see if a check constraint exists.
     *
     * @param string $tableName Table name
     * @param string $constraintName Constraint name
     * @return bool
     */
    public function hasCheckConstraint(string $tableName, string $constraintName): bool;

    /**
     * Adds a check constraint to a database table.
     *
     * @param \Migrations\Db\Table\TableMetadata $table Table
     * @param \Migrations\Db\Table\CheckConstraint $checkConstraint Check constraint
     * @return void
     */
    public function addCheckConstraint(TableMetadata $table, CheckConstraint $checkConstraint): void;

    /**
     * Drops a check constraint from a database table.
     *
     * @param string $tableName Table name
     * @param string $constraintName Constraint name
     * @return void
     */
    public function dropCheckConstraint(string $tableName, string $constraintName): void;

    /**
     * Returns an array of the supported column types.
     *
     * @return string[]
     */
    public function getColumnTypes(): array;

    /**
     * Checks that the given column is of a supported type.
     *
     * @param \Migrations\Db\Table\Column $column Column
     * @return bool
     */
    public function isValidColumnType(Column $column): bool;

    /**
     * Creates a new database.
     *
     * @param string $name Database Name
     * @param array<string, mixed> $options Options
     * @return void
     */
    public function createDatabase(string $name, array $options = []): void;

    /**
     * Checks to see if a database exists.
     *
     * @param string $name Database Name
     * @return bool
     */
    public function hasDatabase(string $name): bool;

    /**
     * Cleanup missing migrations from the migration tracking table.
     *
     * Removes entries from the migrations table for migrations that no longer exist
     * in the migrations directory (marked as MISSING in status output).
     *
     * @return void
     */
    public function cleanupMissing(array $missingVersions): void;

    /**
     * Drops the specified database.
     *
     * @param string $name Database Name
     * @return void
     */
    public function dropDatabase(string $name): void;

    /**
     * Creates the specified schema or throws an exception
     * if there is no support for it.
     *
     * @param string $schemaName Schema Name
     * @return void
     */
    public function createSchema(string $schemaName = 'public'): void;

    /**
     * Drops the specified schema table or throws an exception
     * if there is no support for it.
     *
     * @param string $schemaName Schema name
     * @return void
     */
    public function dropSchema(string $schemaName): void;

    /**
     * Cast a value to a boolean appropriate for the adapter.
     *
     * @param mixed $value The value to be cast
     * @return mixed
     */
    public function castToBool(mixed $value): mixed;

    /**
     * Sets the consoleio.
     *
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     * @return $this
     */
    public function setIo(ConsoleIo $io);

    /**
     * Get the io instance
     *
     * @return \Cake\Console\ConsoleIo $io The io instance to use
     */
    public function getIo(): ?ConsoleIo;

    /**
     * Get the Connection for this adapter.
     *
     * @return \Cake\Database\Connection The connection
     */
    public function getConnection(): Connection;

    /**
     * Gets the schema table name.
     *
     * Returns the table name used for migration tracking based on configuration:
     * - 'cake_migrations' for unified mode
     * - 'phinxlog' or '{plugin}_phinxlog' for legacy mode
     *
     * @return string The migration tracking table name
     */
    public function getSchemaTableName(): string;
}
