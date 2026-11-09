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
namespace Migrations\Command;

use Cake\Command\Command;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventDispatcherTrait;
use Migrations\Config\ConfigInterface;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Adapter\DirectActionInterface;
use Migrations\Migration\ManagerFactory;
use RuntimeException;
use Throwable;

/**
 * Reset command drops all tables and re-runs all migrations.
 *
 * This is a destructive operation intended for development use.
 */
class ResetCommand extends Command
{
    use EventDispatcherTrait;

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Reset database state by dropping tables, and re-running migrations.';
    }

    /**
     * The default name added to the application command list
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'migrations reset';
    }

    /**
     * Configure the option parser
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The option parser to configure
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription([
            'Drop all tables and re-run all migrations.',
            '',
            '<warning>This is a destructive operation!</warning>',
            'All data in the database will be lost.',
            '',
            '<info>migrations reset</info>',
            '<info>migrations reset -c secondary</info>',
            '<info>migrations reset --dry-run</info>',
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'The plugin to run migrations for',
        ])->addOption('connection', [
            'short' => 'c',
            'help' => 'The datasource connection to use',
            'default' => 'default',
        ])->addOption('source', [
            'short' => 's',
            'default' => ConfigInterface::DEFAULT_MIGRATION_FOLDER,
            'help' => 'The folder where your migrations are',
        ])->addOption('dry-run', [
            'short' => 'x',
            'help' => 'Preview what tables would be dropped without making changes',
            'boolean' => true,
        ])->addOption('no-lock', [
            'help' => 'If present, no lock file will be generated after migrating',
            'boolean' => true,
        ]);

        return $parser;
    }

    /**
     * Execute the command.
     *
     * @return int|null The exit code or null for success
     */
    public function execute(): ?int
    {
        $event = $this->dispatchEvent('Migration.beforeReset');
        if ($event->isStopped()) {
            return $event->getResult() ? self::CODE_SUCCESS : self::CODE_ERROR;
        }
        $connectionName = (string)$this->args->getOption('connection');
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get($connectionName);
        $dryRun = (bool)$this->args->getOption('dry-run');
        if ($dryRun) {
            $this->io->out('<warning>DRY-RUN mode enabled - no changes will be made</warning>');
            $this->io->out('');
        }
        // Get tables to drop
        $tablesToDrop = $this->getTablesToDrop($connection);
        if ($tablesToDrop === []) {
            $this->io->out('<info>No tables to drop.</info>');
            $this->io->out('');
            $this->io->out('Running migrations...');

            return $this->runMigrationsAndDispatch();
        }
        // Show what will be dropped
        $this->io->out('<warning>The following tables will be dropped:</warning>');
        foreach ($tablesToDrop as $table) {
            $this->io->out('  - ' . $table);
        }
        $this->io->out('');
        // Ask for confirmation (unless dry-run)
        if (!$dryRun) {
            $continue = $this->io->askChoice(
                'This will permanently delete all data. Do you want to continue?',
                ['y', 'n'],
                'n',
            );
            if ($continue !== 'y') {
                $this->io->warning('Reset operation aborted.');

                return self::CODE_SUCCESS;
            }
        }
        // Drop tables
        $this->io->out('');
        if (!$dryRun) {
            $factory = new ManagerFactory([
                'plugin' => $this->args->getOption('plugin'),
                'source' => $this->args->getOption('source'),
                'connection' => $this->args->getOption('connection'),
            ]);
            $manager = $factory->createManager($this->io);
            $adapter = $manager->getEnvironment()->getAdapter();

            $this->dropTables($adapter, $tablesToDrop);
        } else {
            $this->io->info('DRY-RUN: Would drop ' . count($tablesToDrop) . ' table(s).');
        }
        $this->io->out('');
        // Re-run migrations
        if (!$dryRun) {
            return $this->runMigrationsAndDispatch();
        }
        $this->io->info('DRY-RUN: Would re-run all migrations.');

        return self::CODE_SUCCESS;
    }

    /**
     * Get list of tables to drop.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @return array<string> List of table names
     */
    protected function getTablesToDrop(Connection $connection): array
    {
        $schema = $connection->getDriver()->schemaDialect();

        return $schema->listTables();
    }

    /**
     * Drop tables with foreign key handling.
     *
     * @param \Migrations\Db\Adapter\AdapterInterface $adapter The adapter
     * @param array<string> $tables Tables to drop
     * @return void
     */
    protected function dropTables(AdapterInterface $adapter, array $tables): void
    {
        if (!$adapter instanceof DirectActionInterface) {
            throw new RuntimeException('The adapter must implement DirectActionInterface');
        }

        $adapter->disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                $this->io->verbose('Dropping table: ' . $table);
                $adapter->dropTable($table);
            }
        } finally {
            $adapter->enableForeignKeyConstraints();
        }

        $this->io->success('Dropped ' . count($tables) . ' table(s).');
    }

    /**
     * Run migrations and dispatch afterReset event.
     *
     * @return int|null The exit code
     */
    protected function runMigrationsAndDispatch(): ?int
    {
        $result = $this->runMigrations();
        $this->dispatchEvent('Migration.afterReset');

        return $result;
    }

    /**
     * Run migrations.
     *
     * @return int|null The exit code
     */
    protected function runMigrations(): ?int
    {
        $factory = new ManagerFactory([
            'plugin' => $this->args->getOption('plugin'),
            'source' => $this->args->getOption('source'),
            'connection' => $this->args->getOption('connection'),
            'dry-run' => (bool)$this->args->getOption('dry-run'),
        ]);
        $manager = $factory->createManager($this->io);
        $config = $manager->getConfig();
        $this->io->verbose('<info>using connection</info> ' . $this->args->getOption('connection'));
        $this->io->verbose('<info>using paths</info> ' . $config->getMigrationPath());
        try {
            $start = microtime(true);
            $manager->migrate(null, false);
            $end = microtime(true);
        } catch (Throwable $e) {
            $this->io->err('<error>' . $e->getMessage() . '</error>');
            $this->io->verbose($e->getTraceAsString());

            return self::CODE_ERROR;
        }
        $this->io->comment('All Done. Took ' . sprintf('%.4fs', $end - $start));
        $this->io->out('');

        $exitCode = self::CODE_SUCCESS;
        // Run dump command to generate lock file
        if (!$this->args->getOption('no-lock') && !$this->args->getOption('dry-run')) {
            $this->io->verbose('');
            $this->io->verbose('Dumping the current schema of the database to be used while baking a diff');
            $this->io->verbose('');

            $newArgs = DumpCommand::extractArgs($this->args);
            $exitCode = $this->executeCommand(DumpCommand::class, $newArgs);
        }

        return $exitCode;
    }
}
