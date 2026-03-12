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
use Migrations\Command\ResetCommand;
use Migrations\Command\RollbackCommand;
use Migrations\Command\SeedCommand;
use Migrations\Command\SeedResetCommand;
use Migrations\Command\SeedsEntryCommand;
use Migrations\Command\SeedStatusCommand;
use Migrations\Command\StatusCommand;
use Migrations\Command\UpgradeCommand;

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
     * Initialize configuration with defaults.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The application.
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
    }

    /**
     * Add migrations commands.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $migrationClasses = [
            EntryCommand::class,
            DumpCommand::class,
            MarkMigratedCommand::class,
            MigrateCommand::class,
            ResetCommand::class,
            RollbackCommand::class,
            StatusCommand::class,
        ];

        // Only show upgrade command if not explicitly using unified table
        // (i.e., when legacyTables is null/autodetect or true)
        if (Configure::read('Migrations.legacyTables') !== false) {
            $migrationClasses[] = UpgradeCommand::class;
        }

        $seedClasses = [
            SeedsEntryCommand::class,
            SeedCommand::class,
            SeedResetCommand::class,
            SeedStatusCommand::class,
        ];
        $hasBake = class_exists(SimpleBakeCommand::class);
        if ($hasBake) {
            $migrationClasses[] = BakeMigrationCommand::class;
            $migrationClasses[] = BakeMigrationDiffCommand::class;
            $migrationClasses[] = BakeMigrationSnapshotCommand::class;
            $migrationClasses[] = BakeSeedCommand::class;
        }
        $found = [];
        foreach ($migrationClasses as $class) {
            $name = $class::defaultName();
            // If the short name has been used, use the full name.
            // This allows app commands to have name preference.
            // and app commands to overwrite migration commands.
            if (!$commands->has($name)) {
                $found[$name] = $class;
            }
            $found['migrations.' . $name] = $class;
        }
        foreach ($seedClasses as $class) {
            $name = $class::defaultName();
            if (!$commands->has($name)) {
                $found[$name] = $class;
            }
            $found['seeds.' . $name] = $class;
        }
        if ($hasBake) {
            $found['migrations create'] = BakeMigrationCommand::class;
        }

        $commands->addMany($found);

        return $commands;
    }
}
