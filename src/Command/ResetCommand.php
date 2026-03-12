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
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventDispatcherTrait;
use Migrations\Config\ConfigInterface;
use Migrations\Migration\ManagerFactory;
use Throwable;

/**
 * Reset command drops all tables and re-runs all migrations.
 *
 * This is a destructive operation intended for development use.
 */
class ResetCommand extends Command
{
    /**
     * @use \Cake\Event\EventDispatcherTrait<\Migrations\Command\ResetCommand>
     */
    use EventDispatcherTrait;

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
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
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
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $event = $this->dispatchEvent('Migration.beforeReset');
        if ($event->isStopped()) {
            return $event->getResult() ? self::CODE_SUCCESS : self::CODE_ERROR;
        }

        $connectionName = (string)$args->getOption('connection');
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get($connectionName);
        $dryRun = (bool)$args->getOption('dry-run');

        if ($dryRun) {
            $io->out('<warning>DRY-RUN mode enabled - no changes will be made</warning>');
            $io->out('');
        }

        // Get tables to drop
        $tablesToDrop = $this->getTablesToDrop($connection);

        if (empty($tablesToDrop)) {
            $io->out('<info>No tables to drop.</info>');
            $io->out('');
            $io->out('Running migrations...');

            $result = $this->runMigrations($args, $io);
            $this->dispatchEvent('Migration.afterReset');

            return $result;
        }

        // Show what will be dropped
        $io->out('<warning>The following tables will be dropped:</warning>');
        foreach ($tablesToDrop as $table) {
            $io->out('  - ' . $table);
        }
        $io->out('');

        // Ask for confirmation (unless dry-run)
        if (!$dryRun) {
            $continue = $io->askChoice(
                'This will permanently delete all data. Do you want to continue?',
                ['y', 'n'],
                'n',
            );
            if ($continue !== 'y') {
                $io->warning('Reset operation aborted.');

                return self::CODE_SUCCESS;
            }
        }

        // Drop tables
        $io->out('');
        if (!$dryRun) {
            $this->dropTables($connection, $tablesToDrop, $io);
        } else {
            $io->info('DRY-RUN: Would drop ' . count($tablesToDrop) . ' table(s).');
        }

        $io->out('');

        // Re-run migrations
        if (!$dryRun) {
            $result = $this->runMigrations($args, $io);
            $this->dispatchEvent('Migration.afterReset');

            return $result;
        }

        $io->info('DRY-RUN: Would re-run all migrations.');

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
     * @param \Cake\Database\Connection $connection Database connection
     * @param array<string> $tables Tables to drop
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function dropTables(Connection $connection, array $tables, ConsoleIo $io): void
    {
        $driver = $connection->getDriver();
        $driverClass = get_class($driver);

        // For PostgreSQL and SQL Server, we need to drop foreign keys first
        // or use CASCADE in the drop statement
        if (str_contains($driverClass, 'Postgres')) {
            foreach ($tables as $table) {
                $quotedTable = $driver->quoteIdentifier($table);
                $io->verbose("Dropping table: {$table}");
                $connection->execute("DROP TABLE IF EXISTS {$quotedTable} CASCADE");
            }
        } elseif (str_contains($driverClass, 'Sqlserver')) {
            // Drop all foreign key constraints first
            $this->dropForeignKeyConstraints($connection, $tables, $io);

            // Then drop tables
            foreach ($tables as $table) {
                $quotedTable = $driver->quoteIdentifier($table);
                $io->verbose("Dropping table: {$table}");
                $connection->execute("DROP TABLE IF EXISTS {$quotedTable}");
            }
        } else {
            // MySQL and SQLite support disabling foreign key checks
            $this->setForeignKeyChecks($connection, false);

            try {
                foreach ($tables as $table) {
                    $quotedTable = $driver->quoteIdentifier($table);
                    $io->verbose("Dropping table: {$table}");
                    $connection->execute("DROP TABLE IF EXISTS {$quotedTable}");
                }
            } finally {
                $this->setForeignKeyChecks($connection, true);
            }
        }

        $io->success('Dropped ' . count($tables) . ' table(s).');
    }

    /**
     * Drop all foreign key constraints from the given tables.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param array<string> $tables Tables to process
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function dropForeignKeyConstraints(Connection $connection, array $tables, ConsoleIo $io): void
    {
        $driver = $connection->getDriver();
        $driverClass = get_class($driver);

        if (!str_contains($driverClass, 'Sqlserver')) {
            return;
        }

        // Query to find all foreign key constraints on the specified tables
        $tableList = implode("','", array_map(fn($t) => addslashes($t), $tables));

        $sql = "SELECT
                    fk.name AS constraint_name,
                    OBJECT_NAME(fk.parent_object_id) AS table_name
                FROM sys.foreign_keys fk
                WHERE OBJECT_NAME(fk.parent_object_id) IN ('{$tableList}')";

        $result = $connection->execute($sql)->fetchAll('assoc');

        foreach ($result as $row) {
            $constraintName = $driver->quoteIdentifier($row['constraint_name']);
            $tableName = $driver->quoteIdentifier($row['table_name']);
            $io->verbose("Dropping foreign key: {$row['constraint_name']} on {$row['table_name']}");
            $connection->execute("ALTER TABLE {$tableName} DROP CONSTRAINT {$constraintName}");
        }
    }

    /**
     * Enable or disable foreign key checks.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param bool $enable Whether to enable or disable
     * @return void
     */
    protected function setForeignKeyChecks(Connection $connection, bool $enable): void
    {
        $driver = $connection->getDriver();
        $driverClass = get_class($driver);

        if (str_contains($driverClass, 'Mysql')) {
            $connection->execute('SET FOREIGN_KEY_CHECKS = ' . ($enable ? '1' : '0'));
        } elseif (str_contains($driverClass, 'Sqlite')) {
            $connection->execute('PRAGMA foreign_keys = ' . ($enable ? 'ON' : 'OFF'));
        }
    }

    /**
     * Run migrations.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code
     */
    protected function runMigrations(Arguments $args, ConsoleIo $io): ?int
    {
        $factory = new ManagerFactory([
            'plugin' => $args->getOption('plugin'),
            'source' => $args->getOption('source'),
            'connection' => $args->getOption('connection'),
            'dry-run' => (bool)$args->getOption('dry-run'),
        ]);

        $manager = $factory->createManager($io);
        $config = $manager->getConfig();

        $io->verbose('<info>using connection</info> ' . (string)$args->getOption('connection'));
        $io->verbose('<info>using paths</info> ' . $config->getMigrationPath());

        try {
            $start = microtime(true);
            $manager->migrate(null, false, null);
            $end = microtime(true);
        } catch (Throwable $e) {
            $io->err('<error>' . $e->getMessage() . '</error>');
            $io->verbose($e->getTraceAsString());

            return self::CODE_ERROR;
        }

        $io->comment('All Done. Took ' . sprintf('%.4fs', $end - $start));
        $io->out('');

        $exitCode = self::CODE_SUCCESS;

        // Run dump command to generate lock file
        if (!$args->getOption('no-lock') && !$args->getOption('dry-run')) {
            $io->verbose('');
            $io->verbose('Dumping the current schema of the database to be used while baking a diff');
            $io->verbose('');

            $newArgs = DumpCommand::extractArgs($args);
            $exitCode = $this->executeCommand(DumpCommand::class, $newArgs, $io);
        }

        return $exitCode;
    }
}
