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
use Migrations\Migration\ManagerFactory;
use RuntimeException;
use function Cake\Core\pluginSplit;

/**
 * Base seed implementation
 *
 * Provides base functionality for seeds to extend.
 */
class BaseSeed implements SeedInterface
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
     * No-op constructor.
     */
    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getDependencies(): array
    {
        return [];
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
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->getAdapter()->execute($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, array $params = []): mixed
    {
        return $this->getAdapter()->query($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->getAdapter()->fetchRow($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll(string $sql): array
    {
        return $this->getAdapter()->fetchAll($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function insert(string $tableName, array $data): void
    {
        // convert to table object
        $table = new Table($tableName, [], $this->getAdapter());
        $table->insert($data)->save();
    }

    /**
     * {@inheritDoc}
     */
    public function hasTable(string $tableName): bool
    {
        return $this->getAdapter()->hasTable($tableName);
    }

    /**
     * {@inheritDoc}
     */
    public function table(string $tableName, array $options = []): Table
    {
        return new Table($tableName, $options, $this->getAdapter());
    }

    /**
     * {@inheritDoc}
     */
    public function shouldExecute(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function call(string $seeder, array $options = []): void
    {
        $io = $this->getIo();
        assert($io !== null, 'Requires ConsoleIo');
        $io->out('');
        $io->out(
            ' ====' .
            ' <info>' . $seeder . ':</info>' .
            ' <comment>seeding</comment>'
        );

        $start = microtime(true);
        $this->runCall($seeder, $options);
        $end = microtime(true);

        $io->out(
            ' ====' .
            ' <info>' . $seeder . ':</info>' .
            ' <comment>seeded' .
            ' ' . sprintf('%.4fs', $end - $start) . '</comment>'
        );
        $io->out('');
    }

    /**
     * Calls another seeder from this seeder.
     * It will load the Seed class you are calling and run it.
     *
     * @param string $seeder Name of the seeder to call from the current seed
     * @param array $options The CLI options passed to ManagerFactory.
     * @return void
     */
    protected function runCall(string $seeder, array $options = []): void
    {
        [$pluginName, $seeder] = pluginSplit($seeder);
        $adapter = $this->getAdapter();
        $connection = $adapter->getConnection()->configName();

        $factory = new ManagerFactory([
            'plugin' => $options['plugin'] ?? $pluginName ?? null,
            'source' => $options['source'] ?? null,
            'connection' => $options['connection'] ?? $connection,
        ]);
        $io = $this->getIo();
        assert($io !== null, 'Missing ConsoleIo instance');
        $manager = $factory->createManager($io);
        $manager->seed($seeder);
    }
}
