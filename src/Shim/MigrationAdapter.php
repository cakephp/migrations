<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Shim;

use Cake\Console\ConsoleIo;
use Cake\Database\Query;
use Cake\Database\Query\DeleteQuery;
use Cake\Database\Query\InsertQuery;
use Cake\Database\Query\SelectQuery;
use Cake\Database\Query\UpdateQuery;
use Migrations\Config\ConfigInterface;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Adapter\PhinxAdapter;
use Migrations\Db\Table;
use Migrations\MigrationInterface;
use Phinx\Db\Adapter\AdapterFactory as PhinxAdapterFactory;
use Phinx\Migration\MigrationInterface as PhinxMigrationInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Migration interface.
 *
 * Implements the same methods as phinx but with Migrations classes.
 */
class MigrationAdapter implements MigrationInterface
{
    /**
     * A migrations adapter instance
     *
     * @var \Migrations\Db\Adapter\AdapterInterface|null
     */
    protected ?AdapterInterface $adapter = null;

    /**
     * A migrations configuration instance
     *
     * @var \Migrations\Config\ConfigInterface|null
     */
    protected ?ConfigInterface $config = null;

    /**
     * A ConsoleIo instance
     *
     * @var \Cake\Console\ConsoleIo|null
     */
    protected ?ConsoleIo $io = null;

    /**
     * The wrapped phinx Migration
     *
     * @var \Phinx\Migration\MigrationInterface
     */
    protected PhinxMigrationInterface $migration;

    /**
     * Constructor
     *
     * @param string|object $migrationClass The phinx migration to adapt
     * @param int $version The migration version
     * @param \Cake\Console\ConsoleIo $io The io
     */
    public function __construct(
        string|object $migrationClass,
        int $version,
    ) {
        if (is_string($migrationClass)) {
            if (!is_subclass_of($migrationClass, PhinxMigrationInterface::class)) {
                throw new RuntimeException(
                    'The provided $migrationClass must be a ' .
                    'subclass of Phinx\Migration\MigrationInterface'
                );
            }
            $this->migration = new $migrationClass('default', $version);
        } else {
            if (!is_subclass_of($migrationClass, PhinxMigrationInterface::class)) {
                throw new RuntimeException(
                    'The provided $migrationClass must be a ' .
                    'subclass of Phinx\Migration\MigrationInterface'
                );
            }
            $this->migration = $migrationClass;
        }
    }

    /**
     * Because we're a compatibility shim, we implement this hook
     * so that it can be conditionally called when it is implemented.
     *
     * @return void
     */
    public function init(): void
    {
        if (method_exists($this->migration, MigrationInterface::INIT)) {
            $this->migration->{MigrationInterface::INIT}();
        }
    }

    /**
     * Compatibility shim for executing change/up/down
     */
    public function applyDirection(string $direction): void
    {
        $adapter = $this->getAdapter();

        // Run the migration
        if (method_exists($this->migration, MigrationInterface::CHANGE)) {
            if ($direction === MigrationInterface::DOWN) {
                // Create an instance of the RecordingAdapter so we can record all
                // of the migration commands for reverse playback
                $adapter = $this->migration->getAdapter();
                assert($adapter !== null, 'Adapter must be set in migration');

                /** @var \Phinx\Db\Adapter\ProxyAdapter $proxyAdapter */
                $proxyAdapter = PhinxAdapterFactory::instance()
                    ->getWrapper('proxy', $adapter);

                // Wrap the adapter with a phinx shim to maintain contain
                $this->migration->setAdapter($proxyAdapter);

                $this->migration->{MigrationInterface::CHANGE}();
                $proxyAdapter->executeInvertedCommands();

                $this->migration->setAdapter($adapter);
            } else {
                $this->migration->{MigrationInterface::CHANGE}();
            }
        } else {
            $this->migration->{$direction}();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $phinxAdapter = new PhinxAdapter($adapter);
        $this->migration->setAdapter($phinxAdapter);
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAdapter(): AdapterInterface
    {
        if (!$this->adapter) {
            throw new RuntimeException('Cannot call getAdapter() until after setAdapter().');
        }

        return $this->adapter;
    }

    /**
     * {@inheritDoc}
     */
    public function setIo(ConsoleIo $io)
    {
        $this->io = $io;
        $this->migration->setOutput(new OutputAdapter($io));

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
        $input = new ArrayInput([
            '--plugin' => $config['plugin'] ?? null,
            '--source' => $config['source'] ?? null,
            '--connection' => $config->getConnection(),
        ]);

        $this->migration->setInput($input);
        $this->config = $config;

        return $this;
    }

    /**
     * Gets the name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->migration->getName();
    }

    /**
     * Sets the migration version number.
     *
     * @param int $version Version
     * @return $this
     */
    public function setVersion(int $version)
    {
        $this->migration->setVersion($version);

        return $this;
    }

    /**
     * Gets the migration version number.
     *
     * @return int
     */
    public function getVersion(): int
    {
        return $this->migration->getVersion();
    }

    /**
     * Sets whether this migration is being applied or reverted
     *
     * @param bool $isMigratingUp True if the migration is being applied
     * @return $this
     */
    public function setMigratingUp(bool $isMigratingUp)
    {
        $this->migration->setMigratingUp($isMigratingUp);

        return $this;
    }

    /**
     * Gets whether this migration is being applied or reverted.
     * True means that the migration is being applied.
     *
     * @return bool
     */
    public function isMigratingUp(): bool
    {
        return $this->migration->isMigratingUp();
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
        return $this->migration->execute($sql, $params);
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
        return $this->migration->query($sql, $params);
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
        return $this->migration->getQueryBuilder($type);
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
        return $this->migration->getSelectBuilder();
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
        return $this->migration->getInsertBuilder();
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
        return $this->migration->getUpdateBuilder();
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
        return $this->migration->getDeleteBuilder();
    }

    /**
     * Executes a query and returns only one row as an array.
     *
     * @param string $sql SQL
     * @return array|false
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->migration->fetchRow($sql);
    }

    /**
     * Executes a query and returns an array of rows.
     *
     * @param string $sql SQL
     * @return array
     */
    public function fetchAll(string $sql): array
    {
        return $this->migration->fetchAll($sql);
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
        $this->migration->createDatabase($name, $options);
    }

    /**
     * Drop a database.
     *
     * @param string $name Database Name
     * @return void
     */
    public function dropDatabase(string $name): void
    {
        $this->migration->dropDatabase($name);
    }

    /**
     * {@inheritDoc}
     */
    public function createSchema(string $name): void
    {
        $this->migration->createSchema($name);
    }

    /**
     * {@inheritDoc}
     */
    public function dropSchema(string $name): void
    {
        $this->migration->dropSchema($name);
    }

    /**
     * {@inheritDoc}
     */
    public function hasTable(string $tableName): bool
    {
        return $this->migration->hasTable($tableName);
    }

    /**
     * {@inheritDoc}
     */
    public function table(string $tableName, array $options): Table
    {
        throw new RuntimeException('MigrationAdapter::table is not implemented');
    }

    /**
     * {@inheritDoc}
     */
    public function preFlightCheck(): void
    {
        $this->migration->preFlightCheck();
    }

    /**
     * {@inheritDoc}
     */
    public function postFlightCheck(): void
    {
        $this->migration->postFlightCheck();
    }

    /**
     * {@inheritDoc}
     */
    public function shouldExecute(): bool
    {
        return $this->migration->shouldExecute();
    }
}
