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
use Cake\Core\Plugin;
use Cake\Database\Connection;
use Cake\Database\Exception\QueryException;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use Migrations\Db\Adapter\AbstractAdapter;
use Migrations\Db\Adapter\UnifiedMigrationsTableStorage;
use Migrations\Db\Adapter\WrapperInterface;
use Migrations\Migration\ManagerFactory;

/**
 * Upgrade command to migrate from legacy phinxlog tables to unified cake_migrations table.
 *
 * This command is only visible when legacy phinxlog tables are detected
 * or when Migrations.legacyTables is set to true.
 */
class UpgradeCommand extends Command
{
    /**
     * The default name added to the application command list
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'migrations upgrade';
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
            'Upgrades migration tracking from legacy phinxlog tables to unified cake_migrations table.',
            '',
            'This command migrates data from:',
            '  - <info>phinxlog</info> (app migrations)',
            '  - <info>{plugin}_phinxlog</info> (plugin migrations)',
            '',
            'To the unified <info>cake_migrations</info> table with a plugin column.',
            '',
            'After running this command, set <info>Migrations.legacyTables = false</info>',
            'in your configuration to use the new table.',
            '',
            '<info>migrations upgrade --dry-run</info>  Preview changes',
            '<info>migrations upgrade</info>           Execute the upgrade',
        ])->addOption('connection', [
            'short' => 'c',
            'help' => 'The datasource connection to use',
            'default' => 'default',
        ])->addOption('dry-run', [
            'boolean' => true,
            'help' => 'Preview what would be migrated without making changes',
            'default' => false,
        ])->addOption('drop-tables', [
            'boolean' => true,
            'help' => 'Drop legacy phinxlog tables after migration',
            'default' => false,
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
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get((string)$args->getOption('connection'));
        $dryRun = (bool)$args->getOption('dry-run');
        $dropTables = (bool)$args->getOption('drop-tables');

        if ($dryRun) {
            $io->out('<warning>DRY RUN - No changes will be made</warning>');
            $io->out('');
        }

        // Find all legacy phinxlog tables
        $legacyTables = $this->findLegacyTables($connection);

        if ($legacyTables === []) {
            $io->out('<info>No phinxlog tables found. Nothing to upgrade.</info>');

            return self::CODE_SUCCESS;
        }

        $io->out(sprintf('Found <info>%d</info> phinxlog table(s):', count($legacyTables)));
        foreach ($legacyTables as $table => $plugin) {
            $pluginLabel = $plugin === null ? '(app)' : "({$plugin})";
            $io->out("  - {$table} {$pluginLabel}");
        }
        $io->out('');

        // Create unified table if needed
        $unifiedTableName = UnifiedMigrationsTableStorage::TABLE_NAME;
        if (!$this->tableExists($connection, $unifiedTableName)) {
            $io->out("Creating unified table <info>{$unifiedTableName}</info>...");
            if (!$dryRun) {
                $this->createUnifiedTable($connection, $io);
            }
        } else {
            $io->out("Unified table <info>{$unifiedTableName}</info> already exists.");
        }
        $io->out('');

        // Migrate data from each legacy table
        $totalMigrated = 0;
        foreach ($legacyTables as $tableName => $plugin) {
            $count = $this->migrateTable($connection, $tableName, $plugin, $dryRun, $io);
            $totalMigrated += $count;
        }

        $io->out('');
        $io->out(sprintf('Total records migrated: <info>%d</info>', $totalMigrated));

        if (!$dryRun) {
            // Clean up legacy tables
            $io->out('');
            foreach ($legacyTables as $tableName => $plugin) {
                if ($dropTables) {
                    $io->out("Dropping legacy table <info>{$tableName}</info>...");
                    $connection->execute("DROP TABLE {$connection->getDriver()->quoteIdentifier($tableName)}");
                } else {
                    $io->out('Retaining legacy table. You should drop these tables once you have verified your upgrade.');
                }
            }

            $io->out('');
            $io->success('Upgrade complete!');
            $io->out('');
            $io->out('Next steps:');
            if ($dropTables) {
                $io->out('  1. Set <info>\'Migrations\' => [\'legacyTables\' => false]</info> in your config');
                $io->out('  2. Test your application');
            } else {
                $io->out('  1. Test your application');
                $io->out('  2. Drop the phinxlog tables (re-run `bin/cake migrations upgrade --drop-tables`)');
                $io->out('  3. Set <info>\'Migrations\' => [\'legacyTables\' => false]</info> in your config');
            }
        } else {
            $io->out('');
            $io->out('<warning>This was a dry run. Run without --dry-run to execute.</warning>');
        }

        return self::CODE_SUCCESS;
    }

    /**
     * Find all legacy phinxlog tables in the database.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @return array<string, string|null> Map of table name => plugin name (null for app)
     */
    protected function findLegacyTables(Connection $connection): array
    {
        $schema = $connection->getDriver()->schemaDialect();
        $tables = $schema->listTables();
        $legacyTables = [];

        // Build a map of expected table prefixes to plugin names for loaded plugins
        // This allows matching plugins with special characters like CakeDC/Users
        $pluginPrefixMap = $this->buildPluginPrefixMap();

        foreach ($tables as $table) {
            if ($table === 'phinxlog') {
                $legacyTables[$table] = null;
            } elseif (str_ends_with($table, '_phinxlog')) {
                // Extract plugin name from table name
                $prefix = substr($table, 0, -9); // Remove '_phinxlog'

                // Try to match against loaded plugins first
                if (isset($pluginPrefixMap[$prefix])) {
                    $plugin = $pluginPrefixMap[$prefix];
                } else {
                    // Fall back to camelizing the prefix
                    $plugin = Inflector::camelize($prefix);
                }
                $legacyTables[$table] = $plugin;
            }
        }

        return $legacyTables;
    }

    /**
     * Build a map of table prefixes to plugin names for all loaded plugins.
     *
     * This handles plugins with special characters like CakeDC/Users where
     * the table prefix is cake_d_c_users but the plugin name is CakeDC/Users.
     *
     * @return array<string, string> Map of table prefix => plugin name
     */
    protected function buildPluginPrefixMap(): array
    {
        $map = [];
        foreach (Plugin::loaded() as $plugin) {
            $prefix = Inflector::underscore($plugin);
            $prefix = str_replace(['\\', '/', '.'], '_', $prefix);
            $map[$prefix] = $plugin;
        }

        return $map;
    }

    /**
     * Check if a table exists.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param string $tableName Table name
     * @return bool
     */
    protected function tableExists(Connection $connection, string $tableName): bool
    {
        $schema = $connection->getDriver()->schemaDialect();

        return $schema->hasTable($tableName);
    }

    /**
     * Create the unified migrations table.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function createUnifiedTable(Connection $connection, ConsoleIo $io): void
    {
        $factory = new ManagerFactory([
            'plugin' => null,
            'source' => null,
            'connection' => $connection->configName(),
            // This doesn't follow the cli flag as this method is only called when creating the table.
            'dry-run' => false,
        ]);

        $manager = $factory->createManager($io);
        $adapter = $manager->getEnvironment()->getAdapter();
        if ($adapter instanceof WrapperInterface) {
            $adapter = $adapter->getAdapter();
        }
        assert($adapter instanceof AbstractAdapter, 'adapter must be an AbstractAdapter');

        $storage = new UnifiedMigrationsTableStorage($adapter);
        $storage->createTable();
    }

    /**
     * Migrate data from a phinx table to the unified table.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @param string $tableName Legacy table name
     * @param string|null $plugin Plugin name (null for app)
     * @param bool $dryRun Whether this is a dry run
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int Number of records migrated
     */
    protected function migrateTable(
        Connection $connection,
        string $tableName,
        ?string $plugin,
        bool $dryRun,
        ConsoleIo $io,
    ): int {
        $unifiedTable = UnifiedMigrationsTableStorage::TABLE_NAME;
        $pluginLabel = $plugin ?? 'app';

        // Read all records from legacy table
        $query = $connection->selectQuery()
            ->select('*')
            ->from($tableName);
        $rows = $query->execute()->fetchAll('assoc');

        $count = count($rows);
        $io->out("Migrating <info>{$count}</info> record(s) from <info>{$tableName}</info> ({$pluginLabel})...");

        if ($dryRun || $count === 0) {
            return $count;
        }

        // Insert into unified table
        foreach ($rows as $row) {
            try {
                $insertQuery = $connection->insertQuery()
                    ->insert(['version', 'migration_name', 'plugin', 'start_time', 'end_time', 'breakpoint'])
                    ->into($unifiedTable)
                    ->values([
                        'version' => $row['version'],
                        'migration_name' => $row['migration_name'] ?? null,
                        'plugin' => $plugin,
                        'start_time' => $row['start_time'] ?? null,
                        'end_time' => $row['end_time'] ?? null,
                        'breakpoint' => (int)($row['breakpoint'] ?? 0),
                    ]);
                $insertQuery->execute();
            } catch (QueryException $e) {
                $io->out('Already migrated <info>' . $row['migration_name'] . '</info>.');
            }
        }

        return $count;
    }
}
