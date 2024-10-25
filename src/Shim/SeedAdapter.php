<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Shim;

use Cake\Console\ConsoleIo;
use Migrations\Config\ConfigInterface;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Adapter\PhinxAdapter;
use Migrations\Db\Table;
use Migrations\SeedInterface;
use Phinx\Seed\SeedInterface as PhinxSeedInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * Implementation of the Migrations SeedInterface that
 * shims a phinx SeedInterface.
 *
 * This is an internal use shim that allows old phinx based migrations
 * to a migrations interface. In the future we'll need rector rules to
 * upgrade application migrations so that these upgrading is easy when
 * these shims are removed in the next major.
 *
 * This class has several known shortcomings and is not intended
 * to be a robust implementation. Changes to the wrapped Seed
 * will not be reverse propagated for example.
 *
 * @internal
 */
class SeedAdapter implements SeedInterface
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
     * Constructor
     *
     * @param \Phinx\Seed\SeedInterface $seed The seed being decorated
     */
    public function __construct(
        private PhinxSeedInterface $seed
    ) {
    }

    /**
     * Because we're a compatibility shim, we implement this hook
     * so that it can be conditionally called when it is implemented.
     *
     * @return void
     */
    public function init(): void
    {
        if (method_exists($this->seed, PhinxSeedInterface::INIT)) {
            $this->seed->{PhinxSeedInterface::INIT}();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
        $this->seed->run();
    }

    /**
     * {@inheritDoc}
     */
    public function getDependencies(): array
    {
        return $this->seed->getDependencies();
    }

    /**
     * {@inheritDoc}
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $phinxAdapter = new PhinxAdapter($adapter);
        $this->seed->setAdapter($phinxAdapter);
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
        $this->seed->setOutput(new OutputAdapter($io));

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
        $optionDef = new InputDefinition([
            new InputOption('plugin', mode: InputOption::VALUE_OPTIONAL, default: ''),
            new InputOption('connection', mode: InputOption::VALUE_OPTIONAL, default: ''),
            new InputOption('source', mode: InputOption::VALUE_OPTIONAL, default: ''),
        ]);
        $input = new ArrayInput([
            '--plugin' => $config['plugin'] ?? null,
            '--source' => $config['source'] ?? null,
            '--connection' => $config->getConnection(),
        ], $optionDef);

        $this->seed->setInput($input);
        $this->config = $config;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->seed->getName();
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->seed->execute($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, array $params = []): mixed
    {
        return $this->seed->query($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->seed->fetchRow($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll(string $sql): array
    {
        return $this->seed->fetchAll($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function insert(string $tableName, array $data): void
    {
        $this->seed->insert($tableName, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function hasTable(string $tableName): bool
    {
        return $this->seed->hasTable($tableName);
    }

    /**
     * {@inheritDoc}
     */
    public function table(string $tableName, array $options = []): Table
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    public function shouldExecute(): bool
    {
        return $this->seed->shouldExecute();
    }

    /**
     * {@inheritDoc}
     */
    public function call(string $seeder, array $options = []): void
    {
        throw new RuntimeException('Not implemented');
    }
}
