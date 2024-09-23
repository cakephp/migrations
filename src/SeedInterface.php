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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Seed interface
 *
 * Implements the same API as Phinx's SeedInterface does but with migrations classes.
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
     * @return \Migrations\Config\ConfigInterface
     */
    public function getConfig(): ConfigInterface;

    /**
     * Sets the config.
     *
     * @param \Migrations\Config\ConfigInterface $config Configuration Object
     * @return $this
     */
    public function setConfig(ConfigInterface $config);

    /**
     * Gets the input object to be used in migration object
     *
     * A new InputInteface will be generated each time `getOutput` is called.
     *
     * @return \Symfony\Component\Console\Input\InputInterface
     * @deprecated 4.5.0 Use getIo() instead.
     */
    public function getInput(): InputInterface;

    /**
     * Gets the output object to be used in migration object
     *
     * A new OutputInteface will be generated each time `getOutput` is called.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     * @deprecated 4.5.0 Use getIo() instead.
     */
    public function getOutput(): OutputInterface;

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
    public function table(string $tableName, array $options): Table;

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
}
