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
namespace Migrations;

use Migrations\Migration\BackendInterface;
use Migrations\Migration\BuiltinBackend;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Migrations class is responsible for handling migrations command
 * within a non-shell application.
 */
class Migrations
{
    /**
     * The OutputInterface.
     * Should be a \Symfony\Component\Console\Output\NullOutput instance
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected OutputInterface $output;

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
     * Gets the command
     *
     * @return string Command name
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Get the Migrations interface backend.
     *
     * @return \Migrations\Migration\BackendInterface
     */
    protected function getBackend(): BackendInterface
    {
        return new BuiltinBackend($this->default);
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
        $backend = $this->getBackend();

        return $backend->status($options);
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
        $backend = $this->getBackend();

        return $backend->migrate($options);
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
        $backend = $this->getBackend();

        return $backend->rollback($options);
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
        $backend = $this->getBackend();

        return $backend->markMigrated($version, $options);
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
        $backend = $this->getBackend();

        return $backend->seed($options);
    }

    /**
     * Get the input needed for each commands to be run
     *
     * TODO(mark) Remove as part of phinx removal
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
        $className = 'Migrations\Command\\' . $command;
        $options = $arguments + $this->prepareOptions($options);
        /** @var \Symfony\Component\Console\Command\Command $command */
        $command = new $className();
        $definition = $command->getDefinition();

        return new ArrayInput($options, $definition);
    }

    /**
     * Prepares the option to pass on to the InputInterface
     *
     * TODO(mark) Remove as part of phinx removal
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
