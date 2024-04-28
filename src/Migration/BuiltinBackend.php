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

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use DateTime;
use InvalidArgumentException;
use Migrations\Config\ConfigInterface;
use Symfony\Component\Console\Input\ArrayInput;
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
        $config = $manager->getConfig();
        $path = $config->getMigrationPath();

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
        $options['source'] ??= ConfigInterface::DEFAULT_SEED_FOLDER;
        $seed = $options['seed'] ?? null;

        $manager = $this->getManager($options);
        $manager->seed($seed);

        return true;
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
            'source' => $options['source'] ?? ConfigInterface::DEFAULT_MIGRATION_FOLDER,
            'connection' => $options['connection'] ?? 'default',
        ]);
        $io = new ConsoleIo(
            new StubConsoleOutput(),
            new StubConsoleOutput(),
            new StubConsoleInput([]),
        );

        return $factory->createManager($io);
    }
}
