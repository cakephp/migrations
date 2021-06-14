<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\TestSuite;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Schema\SchemaCleaner;
use Cake\TestSuite\Schema\SchemaManager;
use Cake\TestSuite\TestConnectionManager;
use Migrations\Migrations;

class Migrator extends SchemaManager
{
    /**
     * General command to run before your tests run
     * E.g. in tests/bootstrap.php
     *
     * @param array $config Configuration data
     * @param bool  $verbose Set to true to display messages
     * @return static
     */
    public static function migrate(array $config = [], $verbose = false): Migrator
    {
        $migrator = new static($verbose);

        // Ensures that the connections are aliased, in case
        // the migrations invoke the table registry.
        TestConnectionManager::aliasConnections();

        $configReader = new ConfigReader();
        $configReader->readMigrationsInDatasources();
        $configReader->readConfig($config);
        $migrator->handleMigrationsStatus($configReader->getConfig());

        return $migrator;
    }

    /**
     * Run migrations for all configured migrations.
     *
     * @param string[] $config Migration configuration.
     * @return void
     */
    protected function runMigrations(array $config): void
    {
        $migrations = new Migrations();
        $result = $migrations->migrate($config);

        $msg = 'Migrations for ' . $this->stringifyConfig($config);

        if ($result === true) {
            $this->io->success($msg . ' successfully run.');
        } else {
            $this->io->error($msg . ' failed.');
        }
    }

    /**
     * If a migration is missing or down, all tables of the considered connection are dropped.
     *
     * @param array $configs Array of migration configurations to handle.
     * @return $this
     * @throws \Exception
     */
    protected function handleMigrationsStatus(array $configs)
    {
        $connectionsToDrop = [];
        foreach ($configs as &$config) {
            $connectionName = $config['connection'] = $config['connection'] ?? 'test';
            $this->io->info("Reading migrations status for {$this->stringifyConfig($config)}...");
            $migrations = new Migrations($config);
            if ($this->isStatusChanged($migrations)) {
                if (!in_array($connectionName, $connectionsToDrop)) {
                    $connectionsToDrop[] = $connectionName;
                }
            }
        }

        if (empty($connectionsToDrop)) {
            $this->io->success('No migration changes detected.');

            return $this;
        }

        $schemaCleaner = new SchemaCleaner($this->io);
        foreach ($connectionsToDrop as $connectionName) {
            $schemaCleaner->dropTables($connectionName);
        }

        foreach ($configs as $migration) {
            $this->runMigrations($migration);
        }

        // Truncate all created tables, except migration tables
        foreach ($connectionsToDrop as $connectionName) {
            $schema = ConnectionManager::get($connectionName)->getSchemaCollection();
            $allTables = $schema->listTables();
            $tablesToTruncate = $this->unsetMigrationTables($allTables);
            $schemaCleaner->truncateTables($connectionName, $tablesToTruncate);
        }

        return $this;
    }

    /**
     * Unset the phinx migration tables from an array of tables.
     *
     * @param string[] $tables The list of tables to remove items from.
     * @return array
     */
    protected function unsetMigrationTables(array $tables): array
    {
        $endsWithPhinxlog = function (string $string) {
            $needle = 'phinxlog';

            return substr($string, -strlen($needle)) === $needle;
        };

        foreach ($tables as $i => $table) {
            if ($endsWithPhinxlog($table)) {
                unset($tables[$i]);
            }
        }

        return array_values($tables);
    }

    /**
     * Checks if any migrations are up but missing.
     *
     * @param \Migrations\Migrations $migrations The migration collection to check.
     * @return bool
     */
    protected function isStatusChanged(Migrations $migrations): bool
    {
        foreach ($migrations->status() as $migration) {
            if ($migration['status'] === 'up' && ($migration['missing'] ?? false)) {
                $this->io->info('Missing migration(s) detected.');

                return true;
            }
            if ($migration['status'] === 'down') {
                $this->io->info('New migration(s) found.');

                return true;
            }
        }

        return false;
    }

    /**
     * Stringify the migration parameters.
     * This is used to display readable messages
     * on the command line.
     *
     * @param string[] $config Config array
     * @return string
     */
    protected function stringifyConfig(array $config): string
    {
        $options = [];
        foreach (['connection', 'plugin', 'source', 'target'] as $option) {
            if (isset($config[$option])) {
                $options[] = sprintf('%s "%s"', $option, $config[$option]);
            }
        }

        return implode(', ', $options);
    }
}
