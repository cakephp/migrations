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

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;

/**
 * Plugin class for migrations
 */
class Plugin extends BasePlugin
{
    /**
     * Plugin name.
     *
     * @var string
     */
    protected $name = 'Migrations';

    /**
     * Don't try to load routes.
     *
     * @var bool
     */
    protected $routesEnabled = false;

    /**
     * @var array<string>
     * @psalm-var array<class-string<\Cake\Console\BaseCommand>>
     */
    protected $migrationCommandsList = [
        \Migrations\Command\MigrationsCommand::class,
        \Migrations\Command\MigrationsCreateCommand::class,
        \Migrations\Command\MigrationsDumpCommand::class,
        \Migrations\Command\MigrationsMarkMigratedCommand::class,
        \Migrations\Command\MigrationsMigrateCommand::class,
        \Migrations\Command\MigrationsCacheBuildCommand::class,
        \Migrations\Command\MigrationsCacheClearCommand::class,
        \Migrations\Command\MigrationsRollbackCommand::class,
        \Migrations\Command\MigrationsSeedCommand::class,
        \Migrations\Command\MigrationsStatusCommand::class,
    ];

    /**
     * Add migrations commands.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        if (class_exists('Bake\Command\SimpleBakeCommand')) {
            $found = $commands->discoverPlugin($this->getName());

            return $commands->addMany($found);
        }
        $found = [];
        foreach ($this->migrationCommandsList as $class) {
            $name = $class::defaultName();
            // If the short name has been used, use the full name.
            // This allows app commands to have name preference.
            // and app commands to overwrite migration commands.
            if (!$commands->has($name)) {
                $found[$name] = $class;
            }
            // full name
            $found['migrations.' . $name] = $class;
        }

        return $commands->addMany($found);
    }
}
