<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations;

use Cake\Console\ConsoleIo;
use Cake\Database\Query;
use Cake\Database\Query\DeleteQuery;
use Cake\Database\Query\InsertQuery;
use Cake\Database\Query\SelectQuery;
use Cake\Database\Query\UpdateQuery;
use Migrations\Config\ConfigInterface;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Table;
use RuntimeException;

/**
 * Base migration implementation
 *
 * Provides base functionality for migrations to extend
 */
class BaseMigration implements MigrationInterface
{
    /**
     * The Adapter instance
     *
     * @var \Migrations\Db\Adapter\AdapterInterface
     */
    protected ?AdapterInterface $adapter = null;

    /**
     * The ConsoleIo instance
     *
     * @var \Cake\Console\ConsoleIo
     */
    protected ?ConsoleIo $io = null;

    /**
     * The config instance.
     *
     * @var \Migrations\Config\ConfigInterface
     */
    protected ?ConfigInterface $config;

    /**
     * List of all the table objects created by this migration
     *
     * @var array<\Migrations\Db\Table>
     */
    protected array $tables = [];

    /**
     * Is migrating up prop
     *
     * @var bool
     */
    protected bool $isMigratingUp = true;

    /**
     * The version number.
     *
     * @var int
     */
    protected int $version;

    /**
     * Whether the tables created in this migration
     * should auto-create an `id` field or not
     *
     * This option is global for all tables created in the migration file.
     * If you set it to false, you have to manually add the primary keys for your
     * tables using the Migrations\Table::addPrimaryKey() method
     *
     * @var bool
     */
    public bool $autoId = true;

    /**
     * Constructor
     *
     * @param int $version The version this migration is
     */
    public function __construct(int $version)
    {
        $this->validateVersion($version);
        $this->version = $version;
    }

    /**
     * {@inheritDoc}
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAdapter(): AdapterInterface
    {
        if (!$this->adapter) {
            throw new RuntimeException('Adapter not set.');
        }

        return $this->adapter;
    }

    /**
     * {@inheritDoc}
     */
    public function setIo(ConsoleIo $io)
    {
        $this->io = $io;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getIo(): ?ConsoleIo
    {
        return $this->io;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): ?ConfigInterface
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * Sets the migration version number.
     *
     * @param int $version Version
     * @return $this
     */
    public function setVersion(int $version)
    {
        $this->validateVersion($version);
        $this->version = $version;

        return $this;
    }

    /**
     * Gets the migration version number.
     *
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Sets whether this migration is being applied or reverted
     *
     * @param bool $isMigratingUp True if the migration is being applied
     * @return $this
     */
    public function setMigratingUp(bool $isMigratingUp)
    {
        $this->isMigratingUp = $isMigratingUp;

        return $this;
    }

    /**
     * Hook method to decide if this migration should use transactions
     *
     * By default if your driver supports transactions, a transaction will be opened
     * before the migration begins, and commit when the migration completes.
     *
     * @return bool
     */
    public function useTransactions(): bool
    {
        return $this->getAdapter()->hasTransactions();
    }

    /**
     * Gets whether this migration is being applied or reverted.
     * True means that the migration is being applied.
     *
     * @return bool
     */
    public function isMigratingUp(): bool
    {
        return $this->isMigratingUp;
    }

    /**
     * Executes a SQL statement and returns the number of affected rows.
     *
     * @param string $sql SQL
     * @param array $params parameters to use for prepared query
     * @return int
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->getAdapter()->execute($sql, $params);
    }

    /**
     * Executes a SQL statement.
     *
     * The return type depends on the underlying adapter being used. To improve
     * IDE auto-completion possibility, you can overwrite the query method
     * phpDoc in your (typically custom abstract parent) migration class, where
     * you can set the return type by the adapter in your current use.
     *
     * @param string $sql SQL
     * @param array $params parameters to use for prepared query
     * @return mixed
     */
    public function query(string $sql, array $params = []): mixed
    {
        return $this->getAdapter()->query($sql, $params);
    }

    /**
     * Returns a new Query object that can be used to build complex SELECT, UPDATE, INSERT or DELETE
     * queries and execute them against the current database.
     *
     * Queries executed through the query builder are always sent to the database, regardless of the
     * the dry-run settings.
     *
     * @see https://api.cakephp.org/3.6/class-Cake.Database.Query.html
     * @param string $type Query
     * @return \Cake\Database\Query
     */
    public function getQueryBuilder(string $type): Query
    {
        return $this->getAdapter()->getQueryBuilder($type);
    }

    /**
     * Returns a new SelectQuery object that can be used to build complex
     * SELECT queries and execute them against the current database.
     *
     * Queries executed through the query builder are always sent to the database, regardless of the
     * the dry-run settings.
     *
     * @return \Cake\Database\Query\SelectQuery
     */
    public function getSelectBuilder(): SelectQuery
    {
        return $this->getAdapter()->getSelectBuilder();
    }

    /**
     * Returns a new InsertQuery object that can be used to build complex
     * INSERT queries and execute them against the current database.
     *
     * Queries executed through the query builder are always sent to the database, regardless of the
     * the dry-run settings.
     *
     * @return \Cake\Database\Query\InsertQuery
     */
    public function getInsertBuilder(): InsertQuery
    {
        return $this->getAdapter()->getInsertBuilder();
    }

    /**
     * Returns a new UpdateQuery object that can be used to build complex
     * UPDATE queries and execute them against the current database.
     *
     * Queries executed through the query builder are always sent to the database, regardless of the
     * the dry-run settings.
     *
     * @return \Cake\Database\Query\UpdateQuery
     */
    public function getUpdateBuilder(): UpdateQuery
    {
        return $this->getAdapter()->getUpdateBuilder();
    }

    /**
     * Returns a new DeleteQuery object that can be used to build complex
     * DELETE queries and execute them against the current database.
     *
     * Queries executed through the query builder are always sent to the database, regardless of the
     * the dry-run settings.
     *
     * @return \Cake\Database\Query\DeleteQuery
     */
    public function getDeleteBuilder(): DeleteQuery
    {
        return $this->getAdapter()->getDeleteBuilder();
    }

    /**
     * Executes a query and returns only one row as an array.
     *
     * @param string $sql SQL
     * @return array|false
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->getAdapter()->fetchRow($sql);
    }

    /**
     * Executes a query and returns an array of rows.
     *
     * @param string $sql SQL
     * @return array
     */
    public function fetchAll(string $sql): array
    {
        return $this->getAdapter()->fetchAll($sql);
    }

    /**
     * Create a new database.
     *
     * @param string $name Database Name
     * @param array<string, mixed> $options Options
     * @return void
     */
    public function createDatabase(string $name, array $options): void
    {
        $this->getAdapter()->createDatabase($name, $options);
    }

    /**
     * Drop a database.
     *
     * @param string $name Database Name
     * @return void
     */
    public function dropDatabase(string $name): void
    {
        $this->getAdapter()->dropDatabase($name);
    }

    /**
     * Creates schema.
     *
     * This will thrown an error for adapters that do not support schemas.
     *
     * @param string $name Schema name
     * @return void
     * @throws \BadMethodCallException
     */
    public function createSchema(string $name): void
    {
        $this->getAdapter()->createSchema($name);
    }

    /**
     * Drops schema.
     *
     * This will thrown an error for adapters that do not support schemas.
     *
     * @param string $name Schema name
     * @return void
     * @throws \BadMethodCallException
     */
    public function dropSchema(string $name): void
    {
        $this->getAdapter()->dropSchema($name);
    }

    /**
     * Checks to see if a table exists.
     *
     * @param string $tableName Table name
     * @return bool
     */
    public function hasTable(string $tableName): bool
    {
        return $this->getAdapter()->hasTable($tableName);
    }

    /**
     * Returns an instance of the <code>\Table</code> class.
     *
     * You can use this class to create and manipulate tables.
     *
     * @param string $tableName Table name
     * @param array<string, mixed> $options Options
     * @return \Migrations\Db\Table
     */
    public function table(string $tableName, array $options = []): Table
    {
        if ($this->autoId === false) {
            $options['id'] = false;
        }

        $table = new Table($tableName, $options, $this->getAdapter());
        $this->tables[] = $table;

        return $table;
    }

    /**
     * Perform checks on the migration, printing a warning
     * if there are potential problems.
     *
     * @return void
     */
    public function preFlightCheck(): void
    {
        if (method_exists($this, MigrationInterface::CHANGE)) {
            if (
                method_exists($this, MigrationInterface::UP) ||
                method_exists($this, MigrationInterface::DOWN)
            ) {
                $io = $this->getIo();
                if ($io) {
                    $io->out(
                        '<comment>warning</comment> Migration contains both change() and up()/down() methods.' .
                        ' <warning>Ignoring up() and down()</warning>.'
                    );
                }
            }
        }
    }

    /**
     * Perform checks on the migration after completion
     *
     * Right now, the only check is whether all changes were committed
     *
     * @return void
     */
    public function postFlightCheck(): void
    {
        foreach ($this->tables as $table) {
            if ($table->hasPendingActions()) {
                throw new RuntimeException(sprintf('Migration %s_%s has pending actions after execution!', $this->getVersion(), $this->getName()));
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function shouldExecute(): bool
    {
        return true;
    }

    /**
     * Makes sure the version int is within range for valid datetime.
     * This is required to have a meaningful order in the overview.
     *
     * @param int $version Version
     * @return void
     */
    protected function validateVersion(int $version): void
    {
        $length = strlen((string)$version);
        if ($length === 14) {
            return;
        }

        throw new RuntimeException('Invalid version `' . $version . '`, should be in format `YYYYMMDDHHMMSS` (length of 14).');
    }
}
