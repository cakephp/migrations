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
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->migration->getName();
    }

    /**
     * {@inheritDoc}
     */
    public function setVersion(int $version)
    {
        $this->migration->setVersion($version);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): int
    {
        return $this->migration->getVersion();
    }

    /**
     * {@inheritDoc}
     */
    public function useTransactions(): bool
    {
        if (method_exists($this->migration, 'useTransactions')) {
            return $this->migration->useTransactions();
        }

        return $this->migration->getAdapter()->hasTransactions();
    }

    /**
     * {@inheritDoc}
     */
    public function setMigratingUp(bool $isMigratingUp)
    {
        $this->migration->setMigratingUp($isMigratingUp);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isMigratingUp(): bool
    {
        return $this->migration->isMigratingUp();
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->migration->execute($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, array $params = []): mixed
    {
        return $this->migration->query($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function getQueryBuilder(string $type): Query
    {
        return $this->migration->getQueryBuilder($type);
    }

    /**
     * {@inheritDoc}
     */
    public function getSelectBuilder(): SelectQuery
    {
        return $this->migration->getSelectBuilder();
    }

    /**
     * {@inheritDoc}
     */
    public function getInsertBuilder(): InsertQuery
    {
        return $this->migration->getInsertBuilder();
    }

    /**
     * {@inheritDoc}
     */
    public function getUpdateBuilder(): UpdateQuery
    {
        return $this->migration->getUpdateBuilder();
    }

    /**
     * {@inheritDoc}
     */
    public function getDeleteBuilder(): DeleteQuery
    {
        return $this->migration->getDeleteBuilder();
    }

    /**
     * {@inheritDoc}
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->migration->fetchRow($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll(string $sql): array
    {
        return $this->migration->fetchAll($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function createDatabase(string $name, array $options): void
    {
        $this->migration->createDatabase($name, $options);
    }

    /**
     * {@inheritDoc}
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
    public function table(string $tableName, array $options = []): Table
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
