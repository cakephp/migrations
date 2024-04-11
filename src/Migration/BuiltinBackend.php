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

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Datasource\ConnectionManager;
use DateTime;
use InvalidArgumentException;
use Migrations\Command\StatusCommand;
use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Db\Adapter\WrapperInterface;
use Migrations\Migration\Manager;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Migrations class is responsible for handling migrations command
 * within an none-shell application.
 *
 * @internal
 */
class BuiltinBackend
{
    /**
     * The OutputInterface.
     * Should be a \Symfony\Component\Console\Output\NullOutput instance
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected OutputInterface $output;

    /**
     * Manager instance
     *
     * @var \Migrations\Migration\Manager|null
     */
    protected ?Manager $manager = null;

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
     * Returns the status of each migrations based on the options passed
     *
     * @param array<string, mixed> $options Options to pass to the command
     * Available options are :
     *
     * - `format` Format to output the response. Can be 'json'
     * - `connection` The datasource connection to use
     * - `source` The folder where migrations are in
     * - `plugin` The plugin containing the migrations
     *
     * @return array The migrations list and their statuses
     */
    public function status(array $options = []): array
    {
        $manager = $this->getManager($options);

        return $manager->printStatus($options['format'] ?? null);
    }

    /**
     * Migrates available migrations
     *
     * @param array<string, mixed> $options Options to pass to the command
     * Available options are :
     *
     * - `target` The version number to migrate to. If not provided, will migrate
     * everything it can
     * - `connection` The datasource connection to use
     * - `source` The folder where migrations are in
     * - `plugin` The plugin containing the migrations
     * - `date` The date to migrate to
     * @return bool Success
     */
    public function migrate(array $options = []): bool
    {
        $manager = $this->getManager($options);

        if (!empty($options['date'])) {
            $date = new DateTime($options['date']);

            $manager->migrateToDateTime($date);

            return true;
        }

        $manager->migrate($options['target'] ?? null);

        return true;
    }

    /**
     * Rollbacks migrations
     *
     * @param array<string, mixed> $options Options to pass to the command
     * Available options are :
     *
     * - `target` The version number to migrate to. If not provided, will only migrate
     * the last migrations registered in the phinx log
     * - `connection` The datasource connection to use
     * - `source` The folder where migrations are in
     * - `plugin` The plugin containing the migrations
     * - `date` The date to rollback to
     * @return bool Success
     */
    public function rollback(array $options = []): bool
    {
        $manager = $this->getManager($options);

        if (!empty($options['date'])) {
            $date = new DateTime($options['date']);

            $manager->rollbackToDateTime($date);

            return true;
        }

        $manager->rollback($options['target'] ?? null);

        return true;
    }

    /**
     * Marks a migration as migrated
     *
     * @param int|string|null $version The version number of the migration to mark as migrated
     * @param array<string, mixed> $options Options to pass to the command
     * Available options are :
     *
     * - `connection` The datasource connection to use
     * - `source` The folder where migrations are in
     * - `plugin` The plugin containing the migrations
     * @return bool Success
     */
    public function markMigrated(int|string|null $version = null, array $options = []): bool
    {
        if (
            isset($options['target']) &&
            isset($options['exclude']) &&
            isset($options['only'])
        ) {
            $exceptionMessage = 'You should use `exclude` OR `only` (not both) along with a `target` argument';
            throw new InvalidArgumentException($exceptionMessage);
        }
        $args = new Arguments([(string)$version], $options, ['version']);

        $manager = $this->getManager($options);
        $versions = $manager->getVersionsToMark($args);
        $manager->markVersionsAsMigrated($path, $versions);

        return true;
    }

    /**
     * Seed the database using a seed file
     *
     * @param array<string, mixed> $options Options to pass to the command
     * Available options are :
     *
     * - `connection` The datasource connection to use
     * - `source` The folder where migrations are in
     * - `plugin` The plugin containing the migrations
     * - `seed` The seed file to use
     * @return bool Success
     */
    public function seed(array $options = []): bool
    {
        $seed = $options['seed'] ?? null;
        $manager = $this->getManager($options);
        $manager->seed($seed);

        return true;
    }

    /**
     * Runs the method needed to execute and return
     *
     * @param string $method Manager method to call
     * @param array $params Manager params to pass
     * @param \Symfony\Component\Console\Input\InputInterface $input InputInterface needed for the
     * Manager to properly run
     * @return mixed The result of the CakeManager::$method() call
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
     * Returns an instance of Manager
     *
     * @param array $options The options for manager creation
     * @return \Migrations\Migration\Manager Instance of Manager
     */
    public function getManager(array $options): Manager
    {
        $options += $this->default;

        $factory = new ManagerFactory([
            'plugin' => $options['plugin'] ?? null,
            'source' => $options['source'] ?? null,
            'connection' => $options['connection'] ?? 'default',
        ]);
        $io = new ConsoleIo(
            new StubConsoleOutput(),
            new StubConsoleOutput(),
            new StubConsoleInput([]),
        );

        return $factory->createManager($io);
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
        // TODO this could make an array of options for the manager.
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
