<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Migration;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use DateTime;
use InvalidArgumentException;
use Migrations\CakeAdapter;
use Migrations\CakeManager;
use Migrations\ConfigurationTrait;
use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Db\Adapter\WrapperInterface;
use Phinx\Migration\Manager;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Migrations class is responsible for handling migrations command
 * within a non-shell application.
 *
 * @internal
 */
class PhinxBackend implements BackendInterface
{
    use ConfigurationTrait;

    /**
     * The OutputInterface.
     * Should be a \Symfony\Component\Console\Output\NullOutput instance
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected OutputInterface $output;

    /**
     * CakeManager instance
     *
     * @var \Migrations\CakeManager|null
     */
    protected ?CakeManager $manager = null;

    /**
     * Default options to use
     *
     * @var array<string, mixed>
     */
    protected array $default = [];

    /**
     * Current command being run.
     * Useful if some logic needs to be applied in the ConfigurationTrait depending
     * on the command
     *
     * @var string
     */
    protected string $command;

    /**
     * Stub input to feed the manager class since we might not have an input ready when we get the Manager using
     * the `getManager()` method
     *
     * @var \Symfony\Component\Console\Input\ArrayInput
     */
    protected ArrayInput $stubInput;

    /**
     * Constructor
     *
     * @param array<string, mixed> $default Default option to be used when calling a method.
     * Available options are :
     * - `connection` The datasource connection to use
     * - `source` The folder where migrations are in
     * - `plugin` The plugin containing the migrations
     */
    public function __construct(array $default = [])
    {
        $this->output = new NullOutput();
        $this->stubInput = new ArrayInput([]);

        if ($default) {
            $this->default = $default;
        }
    }

    /**
     * Sets the command
     *
     * @param string $command Command name to store.
     * @return $this
     */
    public function setCommand(string $command)
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Sets the input object that should be used for the command class. This object
     * is used to inspect the extra options that are needed for CakePHP apps.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input the input object
     * @return void
     */
    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    /**
     * Gets the command
     *
     * @return string Command name
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * {@inheritDoc}
     */
    public function status(array $options = []): array
    {
        $input = $this->getInput('Status', [], $options);
        $params = ['default', $input->getOption('format')];

        return $this->run('printStatus', $params, $input);
    }

    /**
     * {@inheritDoc}
     */
    public function migrate(array $options = []): bool
    {
        $this->setCommand('migrate');
        $input = $this->getInput('Migrate', [], $options);
        $method = 'migrate';
        $params = ['default', $input->getOption('target')];

        if ($input->getOption('date')) {
            $method = 'migrateToDateTime';
            $params[1] = new DateTime($input->getOption('date'));
        }

        $this->run($method, $params, $input);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function rollback(array $options = []): bool
    {
        $this->setCommand('rollback');
        $input = $this->getInput('Rollback', [], $options);
        $method = 'rollback';
        $params = ['default', $input->getOption('target')];

        if ($input->getOption('date')) {
            $method = 'rollbackToDateTime';
            $params[1] = new DateTime($input->getOption('date'));
        }

        $this->run($method, $params, $input);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function markMigrated(int|string|null $version = null, array $options = []): bool
    {
        $this->setCommand('mark_migrated');

        if (
            isset($options['target']) &&
            isset($options['exclude']) &&
            isset($options['only'])
        ) {
            $exceptionMessage = 'You should use `exclude` OR `only` (not both) along with a `target` argument';
            throw new InvalidArgumentException($exceptionMessage);
        }

        $input = $this->getInput('MarkMigrated', ['version' => $version], $options);
        $this->setInput($input);

        // This will need to vary based on the config option.
        $migrationPaths = $this->getConfig()->getMigrationPaths();
        $config = $this->getConfig(true);
        $params = [
            array_pop($migrationPaths),
            $this->getManager($config)->getVersionsToMark($input),
            $this->output,
        ];

        $this->run('markVersionsAsMigrated', $params, $input);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function seed(array $options = []): bool
    {
        $this->setCommand('seed');
        $input = $this->getInput('Seed', [], $options);

        $seed = $input->getOption('seed');
        if (!$seed) {
            $seed = null;
        }

        $params = ['default', $seed];
        $this->run('seed', $params, $input);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function run(string $method, array $params, InputInterface $input): mixed
    {
        // This will need to vary based on the backend configuration
        if ($this->configuration instanceof Config) {
            $migrationPaths = $this->getConfig()->getMigrationPaths();
            $migrationPath = array_pop($migrationPaths);
            $seedPaths = $this->getConfig()->getSeedPaths();
            $seedPath = array_pop($seedPaths);
        }

        $pdo = null;
        if ($this->manager instanceof Manager) {
            $pdo = $this->manager->getEnvironment('default')
                ->getAdapter()
                ->getConnection();
        }

        $this->setInput($input);
        $newConfig = $this->getConfig(true);
        $manager = $this->getManager($newConfig);
        $manager->setInput($input);

        // Why is this being done? Is this something we can eliminate in the new code path?
        if ($pdo !== null) {
            /** @var \Phinx\Db\Adapter\PdoAdapter|\Migrations\CakeAdapter $adapter */
            /** @psalm-suppress PossiblyNullReference */
            $adapter = $this->manager->getEnvironment('default')->getAdapter();
            while ($adapter instanceof WrapperInterface) {
                /** @var \Phinx\Db\Adapter\PdoAdapter|\Migrations\CakeAdapter $adapter */
                $adapter = $adapter->getAdapter();
            }
            $adapter->setConnection($pdo);
        }

        $newMigrationPaths = $newConfig->getMigrationPaths();
        if (isset($migrationPath) && array_pop($newMigrationPaths) !== $migrationPath) {
            $manager->resetMigrations();
        }
        $newSeedPaths = $newConfig->getSeedPaths();
        if (isset($seedPath) && array_pop($newSeedPaths) !== $seedPath) {
            $manager->resetSeeds();
        }

        /** @var callable $callable */
        $callable = [$manager, $method];

        return call_user_func_array($callable, $params);
    }

    /**
     * Returns an instance of CakeManager
     *
     * @param \Phinx\Config\ConfigInterface|null $config ConfigInterface the Manager needs to run
     * @return \Migrations\CakeManager Instance of CakeManager
     */
    public function getManager(?ConfigInterface $config = null): CakeManager
    {
        if (!($this->manager instanceof CakeManager)) {
            if (!($config instanceof ConfigInterface)) {
                throw new RuntimeException(
                    'You need to pass a ConfigInterface object for your first getManager() call',
                );
            }

            $input = $this->input ?: $this->stubInput;
            $this->manager = new CakeManager($config, $input, $this->output);
        } elseif ($config !== null) {
            $defaultEnvironment = $config->getEnvironment('default');
            try {
                $environment = $this->manager->getEnvironment('default');
                $oldConfig = $environment->getOptions();
                unset($oldConfig['connection']);
                if ($oldConfig === $defaultEnvironment) {
                    $defaultEnvironment['connection'] = $environment
                        ->getAdapter()
                        ->getConnection();
                }
            } catch (InvalidArgumentException $e) {
            }
            $config['environments'] = ['default' => $defaultEnvironment];
            $this->manager->setEnvironments([]);
            $this->manager->setConfig($config);
        }

        $this->setAdapter();

        return $this->manager;
    }

    /**
     * Sets the adapter the manager is going to need to operate on the DB
     * This will make sure the adapter instance is a \Migrations\CakeAdapter instance
     *
     * @return void
     */
    public function setAdapter(): void
    {
        if ($this->input === null) {
            return;
        }

        $connectionName = $this->input()->getOption('connection') ?: 'default';
        assert(is_string($connectionName), 'Connection name should be a string');
        $connection = ConnectionManager::get($connectionName);
        assert($connection instanceof Connection, 'Connection should be an instance of Cake\Database\Connection');

        /** @psalm-suppress PossiblyNullReference */
        $env = $this->manager->getEnvironment('default');
        $adapter = $env->getAdapter();
        if (!$adapter instanceof CakeAdapter) {
            $env->setAdapter(new CakeAdapter($adapter, $connection));
        }
    }

    /**
     * Get the input needed for each commands to be run
     *
     * @param string $command Command name for which we need the InputInterface
     * @param array<string, mixed> $arguments Simple key/values array representing the command arguments
     * to pass to the InputInterface
     * @param array<string, mixed> $options Simple key/values array representing the command options
     * to pass to the InputInterface
     * @return \Symfony\Component\Console\Input\InputInterface InputInterface needed for the
     * Manager to properly run
     */
    public function getInput(string $command, array $arguments, array $options): InputInterface
    {
        $className = 'Migrations\Command\Phinx\\' . $command;
        $options = $arguments + $this->prepareOptions($options);
        /** @var \Symfony\Component\Console\Command\Command $command */
        $command = new $className();
        $definition = $command->getDefinition();

        return new ArrayInput($options, $definition);
    }

    /**
     * Prepares the option to pass on to the InputInterface
     *
     * @param array<string, mixed> $options Simple key-values array to pass to the InputInterface
     * @return array<string, mixed> Prepared $options
     */
    protected function prepareOptions(array $options = []): array
    {
        $options += $this->default;
        if (!$options) {
            return $options;
        }

        foreach ($options as $name => $value) {
            $options['--' . $name] = $value;
            unset($options[$name]);
        }

        return $options;
    }
}
