<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations;

use Cake\Console\ConsoleIo;
use Migrations\Config\ConfigInterface;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Table;

/**
 * Seed interface
 */
interface SeedInterface
{
    /**
     * @var string
     */
    public const RUN = 'run';

    /**
     * @var string
     */
    public const INIT = 'init';

    /**
     * Run the seeder.
     *
     * @return void
     */
    public function run(): void;

    /**
     * Return seeds dependencies.
     *
     * @return array
     */
    public function getDependencies(): array;

    /**
     * Sets the database adapter.
     *
     * @param \Migrations\Db\Adapter\AdapterInterface $adapter Database Adapter
     * @return $this
     */
    public function setAdapter(AdapterInterface $adapter);

    /**
     * Gets the database adapter.
     *
     * @return \Migrations\Db\Adapter\AdapterInterface
     */
    public function getAdapter(): AdapterInterface;

    /**
     * Set the Console IO object to be used.
     *
     * @param \Cake\Console\ConsoleIo $io The Io
     * @return $this
     */
    public function setIo(ConsoleIo $io);

    /**
     * Get the Console IO object to be used.
     *
     * @return \Cake\Console\ConsoleIo|null
     */
    public function getIo(): ?ConsoleIo;

    /**
     * Gets the config.
     *
     * @return \Migrations\Config\ConfigInterface|null
     */
    public function getConfig(): ?ConfigInterface;

    /**
     * Sets the config.
     *
     * @param \Migrations\Config\ConfigInterface $config Configuration Object
     * @return $this
     */
    public function setConfig(ConfigInterface $config);

    /**
     * Gets the name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Executes a SQL statement and returns the number of affected rows.
     *
     * @param string $sql SQL
     * @param array $params parameters to use for prepared query
     * @return int
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * Executes a SQL statement.
     *
     * The return type depends on the underlying adapter being used. To improve
     * IDE auto-completion possibility, you can overwrite the query method
     * phpDoc in your (typically custom abstract parent) seed class, where
     * you can set the return type by the adapter in your current use.
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
     * Insert data into a table.
     *
     * @param string $tableName Table name
     * @param array $data Data
     * @return void
     */
    public function insert(string $tableName, array $data): void;

    /**
     * Insert data into a table, skipping rows that would cause duplicate key conflicts.
     *
     * This method is idempotent and safe to run multiple times.
     * Uses INSERT IGNORE (MySQL), ON CONFLICT DO NOTHING (PostgreSQL),
     * or INSERT OR IGNORE (SQLite).
     *
     * @param string $tableName Table name
     * @param array $data Data
     * @return void
     */
    public function insertOrSkip(string $tableName, array $data): void;

    /**
     * Insert data into a table, updating specified columns on duplicate key conflicts.
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
     * @param string $tableName Table name
     * @param array $data Data
     * @param array<string> $updateColumns Columns to update when a conflict occurs
     * @param array<string> $conflictColumns Columns that define uniqueness. Required for PostgreSQL/SQLite,
     *   ignored by MySQL (triggers warning if provided).
     * @return void
     * @throws \RuntimeException When using PostgreSQL or SQLite without specifying conflictColumns
     */
    public function insertOrUpdate(string $tableName, array $data, array $updateColumns, array $conflictColumns): void;

    /**
     * Checks to see if a table exists.
     *
     * @param string $tableName Table name
     * @return bool
     */
    public function hasTable(string $tableName): bool;

    /**
     * Returns an instance of the <code>\Table</code> class.
     *
     * You can use this class to create and manipulate tables.
     *
     * @param string $tableName Table name
     * @param array<string, mixed> $options Options
     * @return \Migrations\Db\Table
     */
    public function table(string $tableName, array $options = []): Table;

    /**
     * Checks to see if the seed should be executed.
     *
     * Returns true by default.
     *
     * You can use this to prevent a seed from executing.
     *
     * @return bool
     */
    public function shouldExecute(): bool;

    /**
     * Checks if this seed is idempotent (can run multiple times safely).
     *
     * Returns false by default, meaning the seed will be tracked and only run once.
     *
     * If you return true, the seed will run every time it is invoked.
     * The last execution time is still tracked in the cake_seeds table.
     * Make sure your seed is truly idempotent (handles duplicate data safely)
     * before returning true.
     *
     * @return bool
     */
    public function isIdempotent(): bool;

    /**
     * Gives the ability to a seeder to call another seeder.
     * This is particularly useful if you need to run the seeders of your applications in a specific sequences,
     * for instance to respect foreign key constraints.
     *
     * @param string $seeder Name of the seeder to call from the current seed
     * @param array $options The CLI options for the seeder.
     * @return void
     */
    public function call(string $seeder, array $options = []): void;
}
