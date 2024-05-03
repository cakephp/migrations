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

use Bake\Command\SimpleBakeCommand;
use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Migrations\Command\BakeMigrationCommand;
use Migrations\Command\BakeMigrationDiffCommand;
use Migrations\Command\BakeMigrationSnapshotCommand;
use Migrations\Command\BakeSeedCommand;
use Migrations\Command\DumpCommand;
use Migrations\Command\EntryCommand;
use Migrations\Command\MarkMigratedCommand;
use Migrations\Command\MigrateCommand;
use Migrations\Command\MigrationsCacheBuildCommand;
use Migrations\Command\MigrationsCacheClearCommand;
use Migrations\Command\MigrationsCommand;
use Migrations\Command\MigrationsCreateCommand;
use Migrations\Command\MigrationsDumpCommand;
use Migrations\Command\MigrationsMarkMigratedCommand;
use Migrations\Command\MigrationsMigrateCommand;
use Migrations\Command\MigrationsRollbackCommand;
use Migrations\Command\MigrationsSeedCommand;
use Migrations\Command\MigrationsStatusCommand;
use Migrations\Command\RollbackCommand;
use Migrations\Command\SeedCommand;
use Migrations\Command\StatusCommand;

/**
 * Plugin class for migrations
 */
class MigrationsPlugin extends BasePlugin
{
    /**
     * Plugin name.
     */
    protected ?string $name = 'Migrations';

    /**
     * Don't try to load routes.
     */
    protected bool $routesEnabled = false;

    /**
     * @var array<string>
     * @psalm-var array<class-string<\Cake\Console\BaseCommand>>
     */
    protected array $migrationCommandsList = [
        MigrationsCommand::class,
        MigrationsCreateCommand::class,
        MigrationsDumpCommand::class,
        MigrationsMarkMigratedCommand::class,
        MigrationsMigrateCommand::class,
        MigrationsCacheBuildCommand::class,
        MigrationsCacheClearCommand::class,
        MigrationsRollbackCommand::class,
        MigrationsSeedCommand::class,
        MigrationsStatusCommand::class,
    ];

    /**
     * Initialize configuration with defaults.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The application.
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if (!Configure::check('Migrations.backend')) {
            Configure::write('Migrations.backend', 'phinx');
        }
    }

    /**
     * Add migrations commands.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        if (Configure::read('Migrations.backend') == 'builtin') {
            $classes = [
                DumpCommand::class,
                EntryCommand::class,
                MarkMigratedCommand::class,
                MigrateCommand::class,
                RollbackCommand::class,
                SeedCommand::class,
                StatusCommand::class,
            ];
            $hasBake = class_exists(SimpleBakeCommand::class);
            if ($hasBake) {
                $classes[] = BakeMigrationCommand::class;
                $classes[] = BakeMigrationDiffCommand::class;
                $classes[] = BakeMigrationSnapshotCommand::class;
                $classes[] = BakeSeedCommand::class;
            }
            $found = [];
            foreach ($classes as $class) {
                $name = $class::defaultName();
                // If the short name has been used, use the full name.
                // This allows app commands to have name preference.
                // and app commands to overwrite migration commands.
                if (!$commands->has($name)) {
                    $found[$name] = $class;
                }
                $found['migrations.' . $name] = $class;
            }
            if ($hasBake) {
                $found['migrations create'] = BakeMigrationCommand::class;
            }

            $commands->addMany($found);

            return $commands;
        } else {
            if (class_exists(SimpleBakeCommand::class)) {
                $found = $commands->discoverPlugin($this->getName());

                return $commands->addMany($found);
            }
            $found = [];
            // Convert to a method and use config to toggle command names.
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
}
