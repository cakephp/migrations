<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Migration;

use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use Migrations\Db\Adapter\AdapterFactory;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Adapter\PhinxAdapter;
use Phinx\Migration\MigrationInterface;
use Phinx\Seed\SeedInterface;
use RuntimeException;

class Environment
{
    /**
     * @var string
     */
    protected string $name;

    /**
     * @var array<string, mixed>
     */
    protected array $options;

    /**
     * @var \Cake\Console\ConsoleIo|null
     */
    protected ?ConsoleIo $io = null;

    /**
     * @var int
     */
    protected int $currentVersion;

    /**
     * @var string
     */
    protected string $schemaTableName = 'phinxlog';

    /**
     * @var \Migrations\Db\Adapter\AdapterInterface
     */
    protected AdapterInterface $adapter;

    /**
     * @param string $name Environment Name
     * @param array<string, mixed> $options Options
     */
    public function __construct(string $name, array $options)
    {
        $this->name = $name;
        $this->options = $options;
    }

    /**
     * Executes the specified migration on this environment.
     *
     * @param \Phinx\Migration\MigrationInterface $migration Migration
     * @param string $direction Direction
     * @param bool $fake flag that if true, we just record running the migration, but not actually do the migration
     * @return void
     */
    public function executeMigration(MigrationInterface $migration, string $direction = MigrationInterface::UP, bool $fake = false): void
    {
        $direction = $direction === MigrationInterface::UP ? MigrationInterface::UP : MigrationInterface::DOWN;
        $migration->setMigratingUp($direction === MigrationInterface::UP);

        $startTime = time();
        // Use an adapter shim to bridge between the new migrations
        // engine and the Phinx compatible interface
        $adapter = $this->getAdapter();
        $phinxShim = new PhinxAdapter($adapter);
        $migration->setAdapter($phinxShim);

        $migration->preFlightCheck();

        if (method_exists($migration, MigrationInterface::INIT)) {
            $migration->{MigrationInterface::INIT}();
        }

        $atomic = $adapter->hasTransactions();
        if (method_exists($migration, 'useTransactions')) {
            $atomic = $migration->useTransactions();
        }
        // begin the transaction if the adapter supports it
        if ($atomic) {
            $adapter->beginTransaction();
        }

        if (!$fake) {
            // Run the migration
            if (method_exists($migration, MigrationInterface::CHANGE)) {
                if ($direction === MigrationInterface::DOWN) {
                    // Create an instance of the RecordingAdapter so we can record all
                    // of the migration commands for reverse playback

                    /** @var \Migrations\Db\Adapter\RecordingAdapter $recordAdapter */
                    $recordAdapter = AdapterFactory::instance()
                        ->getWrapper('record', $adapter);

                    // Wrap the adapter with a phinx shim to maintain contain
                    $phinxAdapter = new PhinxAdapter($recordAdapter);
                    $migration->setAdapter($phinxAdapter);

                    $migration->{MigrationInterface::CHANGE}();
                    $recordAdapter->executeInvertedCommands();

                    $migration->setAdapter(new PhinxAdapter($this->getAdapter()));
                } else {
                    $migration->{MigrationInterface::CHANGE}();
                }
            } else {
                $migration->{$direction}();
            }
        }

        // Record it in the database
        $adapter->migrated($migration, $direction, date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', time()));

        // commit the transaction if the adapter supports it
        if ($atomic) {
            $adapter->commitTransaction();
        }

        $migration->postFlightCheck();
    }

    /**
     * Executes the specified seeder on this environment.
     *
     * @param \Phinx\Seed\SeedInterface $seed Seed
     * @return void
     */
    public function executeSeed(SeedInterface $seed): void
    {
        $adapter = $this->getAdapter();
        $phinxAdapter = new PhinxAdapter($adapter);

        $seed->setAdapter($phinxAdapter);
        if (method_exists($seed, SeedInterface::INIT)) {
            $seed->{SeedInterface::INIT}();
        }
        // begin the transaction if the adapter supports it
        $atomic = $adapter->hasTransactions();
        if ($atomic) {
            $adapter->beginTransaction();
        }

        // Run the seeder
        if (method_exists($seed, SeedInterface::RUN)) {
            $seed->{SeedInterface::RUN}();
        }

        // commit the transaction if the adapter supports it
        if ($atomic) {
            $adapter->commitTransaction();
        }
    }

    /**
     * Sets the environment's name.
     *
     * @param string $name Environment Name
     * @return $this
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets the environment name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the environment's options.
     *
     * @param array<string, mixed> $options Environment Options
     * @return $this
     */
    public function setOptions(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Gets the environment's options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Sets the consoleio.
     *
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     * @return $this
     */
    public function setIo(ConsoleIo $io)
    {
        $this->io = $io;

        return $this;
    }

    /**
     * Get the io instance
     *
     * @return \Cake\Console\ConsoleIo $io The io instance to use
     */
    public function getIo(): ?ConsoleIo
    {
        return $this->io;
    }

    /**
     * Gets all migrated version numbers.
     *
     * @return array
     */
    public function getVersions(): array
    {
        return $this->getAdapter()->getVersions();
    }

    /**
     * Get all migration log entries, indexed by version creation time and sorted in ascending order by the configuration's
     * version_order option
     *
     * @return array
     */
    public function getVersionLog(): array
    {
        return $this->getAdapter()->getVersionLog();
    }

    /**
     * Sets the current version of the environment.
     *
     * @param int $version Environment Version
     * @return $this
     */
    public function setCurrentVersion(int $version)
    {
        $this->currentVersion = $version;

        return $this;
    }

    /**
     * Gets the current version of the environment.
     *
     * @return int
     */
    public function getCurrentVersion(): int
    {
        // We don't cache this code as the current version is pretty volatile.
        // that means they're no point in a setter then?
        // maybe we should cache and call a reset() method every time a migration is run
        $versions = $this->getVersions();
        $version = 0;

        if (!empty($versions)) {
            $version = end($versions);
        }

        $this->setCurrentVersion($version);

        return $this->currentVersion;
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
        if (isset($this->adapter)) {
            return $this->adapter;
        }

        $options = $this->getOptions();
        if (!isset($options['connection'])) {
            throw new RuntimeException('No connection defined');
        }
        $connection = ConnectionManager::get($options['connection']);
        $options['connection'] = $connection;

        // Get the driver classname as those are aligned with adapter names.
        $driver = $connection->getDriver();
        $driverClass = get_class($driver);
        $driverName = strtolower(substr($driverClass, (int)strrpos($driverClass, '\\') + 1));
        $options['adapter'] = $driverName;

        $factory = AdapterFactory::instance();
        $adapter = $factory
            ->getAdapter($driverName, $options);

        // Automatically time the executed commands
        $adapter = $factory->getWrapper('timed', $adapter);

        if (isset($options['wrapper'])) {
            $adapter = $factory
                ->getWrapper($options['wrapper'], $adapter);
        }

        $io = $this->getIo();
        if ($io) {
            $adapter->setIo($io);
        }
        $this->setAdapter($adapter);

        return $adapter;
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
     * Gets the schema table name.
     *
     * @return string
     */
    public function getSchemaTableName(): string
    {
        return $this->schemaTableName;
    }
}
