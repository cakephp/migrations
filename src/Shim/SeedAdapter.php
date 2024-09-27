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
     * A ConsoleIo instance
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
     * Sets the database adapter.
     *
     * @param \Migrations\Db\Adapter\AdapterInterface $adapter Database Adapter
     * @return $this
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $phinxAdapter = new PhinxAdapter($adapter);
        $this->seed->setAdapter($phinxAdapter);
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Gets the database adapter.
     *
     * @return \Migrations\Db\Adapter\AdapterInterface
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
     * Gets the config.
     *
     * @return ?\Migrations\Config\ConfigInterface
     */
    public function getConfig(): ?ConfigInterface
    {
        return $this->config;
    }

    /**
     * Sets the config.
     *
     * @param \Migrations\Config\ConfigInterface $config Configuration Object
     * @return $this
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
     * Gets the name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->seed->getName();
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
        return $this->seed->execute($sql, $params);
    }

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
    public function query(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params);
    }

    /**
     * Executes a query and returns only one row as an array.
     *
     * @param string $sql SQL
     * @return array|false
     */
    public function fetchRow(string $sql): array|false
    {
        return $this->fetchRow($sql);
    }

    /**
     * Executes a query and returns an array of rows.
     *
     * @param string $sql SQL
     * @return array
     */
    public function fetchAll(string $sql): array
    {
        return $this->fetchAll($sql);
    }

    /**
     * Insert data into a table.
     *
     * @param string $tableName Table name
     * @param array $data Data
     * @return void
     */
    public function insert(string $tableName, array $data): void
    {
        $this->insert($tableName, $data);
    }

    /**
     * Checks to see if a table exists.
     *
     * @param string $tableName Table name
     * @return bool
     */
    public function hasTable(string $tableName): bool
    {
        return $this->hasTable($tableName);
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
    public function table(string $tableName, array $options): Table
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * Checks to see if the seed should be executed.
     *
     * Returns true by default.
     *
     * You can use this to prevent a seed from executing.
     *
     * @return bool
     */
    public function shouldExecute(): bool
    {
        return $this->seed->shouldExecute();
    }

    /**
     * Gives the ability to a seeder to call another seeder.
     * This is particularly useful if you need to run the seeders of your applications in a specific sequences,
     * for instance to respect foreign key constraints.
     *
     * @param string $seeder Name of the seeder to call from the current seed
     * @return void
     */
    public function call(string $seeder): void
    {
        throw new RuntimeException('Not implemented');
    }
}
