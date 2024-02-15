<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Migration;

use Cake\Datasource\ConnectionManager;
use Migrations\Db\Adapter\AdapterFactory;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Adapter\PhinxAdapter;
use PDO;
use Phinx\Migration\MigrationInterface;
use Phinx\Seed\SeedInterface;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @var \Symfony\Component\Console\Input\InputInterface|null
     */
    protected ?InputInterface $input = null;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface|null
     */
    protected ?OutputInterface $output = null;

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

        // begin the transaction if the adapter supports it
        if ($adapter->hasTransactions()) {
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
        if ($adapter->hasTransactions()) {
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
        if ($adapter->hasTransactions()) {
            $adapter->beginTransaction();
        }

        // Run the seeder
        if (method_exists($seed, SeedInterface::RUN)) {
            $seed->{SeedInterface::RUN}();
        }

        // commit the transaction if the adapter supports it
        if ($adapter->hasTransactions()) {
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
     * Sets the console input.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @return $this
     */
    public function setInput(InputInterface $input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Gets the console input.
     *
     * @return \Symfony\Component\Console\Input\InputInterface|null
     */
    public function getInput(): ?InputInterface
    {
        return $this->input;
    }

    /**
     * Sets the console output.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @return $this
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Gets the console output.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface|null
     */
    public function getOutput(): ?OutputInterface
    {
        return $this->output;
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

        // TODO this needs a better API for it.
        // Perhaps a Driver level method
        $driver = $connection->getDriver();
        $reflect = new ReflectionProperty($driver, 'pdo');
        $reflect->setAccessible(true);
        $pdo = $reflect->getValue($driver);
        $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $options['connection'] = $connection;

        $factory = AdapterFactory::instance();
        $adapter = $factory
            ->getAdapter($driverName, $options);

        // Automatically time the executed commands
        $adapter = $factory->getWrapper('timed', $adapter);

        if (isset($options['wrapper'])) {
            $adapter = $factory
                ->getWrapper($options['wrapper'], $adapter);
        }

        /** @var \Symfony\Component\Console\Input\InputInterface|null $input */
        $input = $this->getInput();
        if ($input) {
            $adapter->setInput($input);
        }

        /** @var \Symfony\Component\Console\Output\OutputInterface|null $output */
        $output = $this->getOutput();
        if ($output) {
            $adapter->setOutput($output);
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
