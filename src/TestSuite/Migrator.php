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

use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Schema\SchemaCleaner;
use Cake\TestSuite\TestConnectionManager;
use Migrations\Migrations;

class Migrator
{
    /**
     * @var ConfigReader
     */
    protected $configReader;

    /**
     * @var ConsoleIo
     */
    protected $io;

    /**
     * Migrator constructor.
     * @param bool $verbose
     * @param null $configReader
     */
    final public function __construct(bool $verbose, ?ConfigReader $configReader = null)
    {
        $this->io = new ConsoleIo();
        $this->io->level($verbose ? ConsoleIo::NORMAL : ConsoleIo::QUIET);
        $this->configReader = $configReader ?? new ConfigReader();

        // Make sure that the connections are aliased, in case
        // the migrations invoke the table registry.
        TestConnectionManager::aliasConnections();
    }

    /**
     * General command to run before your tests run
     * E.g. in tests/bootstrap.php
     *
     * @param array $config
     * @param bool  $verbose Set to true to display messages
     * @return Migrator
     */
    public static function migrate(array $config = [], $verbose = false): Migrator
    {
        $migrator = new static($verbose);

        $migrator->configReader->readMigrationsInDatasources();
        $migrator->configReader->readConfig($config);
        $migrator->handleMigrationsStatus();

        return $migrator;
    }

    /**
     * Import the schema from a file, or an array of files.
     *
     * @param string $connectionName Connection
     * @param string|string[] $file File to dump
     * @param bool $verbose Set to true to display messages
     * @return void
     * @throws \Exception if the truncation failed
     * @throws \RuntimeException if the file could not be processed
     */
    public static function dump(string $connectionName, $file, bool $verbose = false)
    {
        $files = (array)$file;

        $migrator = new static($verbose);
        $schemaCleaner = new SchemaCleaner($migrator->io);
        $schemaCleaner->dropTables($connectionName);

        foreach ($files as $file) {
            if (!file_exists($file)) {
                throw new \RuntimeException('The file ' . $file . ' could not found.');
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException('The file ' . $file . ' could not read.');
            }

            ConnectionManager::get($connectionName)->execute($sql);

            $migrator->io->success(
                'Dump of schema in file ' . $file . ' for connection ' . $connectionName . ' successful.'
            );
        }

        $schemaCleaner->truncateTables($connectionName);
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
            $this->io->error( $msg . ' failed.');
        }
    }

    /**
     * If a migration is missing or down, all tables of the considered connection are dropped.
     *
     * @return $this
     * @throws \Exception
     */
    protected function handleMigrationsStatus(): self
    {
        $schemaCleaner = new SchemaCleaner($this->io);
        $connectionsToDrop = [];
        foreach ($this->getConfigs() as &$config) {
            $connectionName = $config['connection'] = $config['connection'] ?? 'test';
            $this->io->info("Reading migrations status for {$this->stringifyConfig($config)}...");
            $migrations = new Migrations($config);
            if ($this->isStatusChanged($migrations)) {
                if (!in_array($connectionName, $connectionsToDrop))
                {
                    $connectionsToDrop[] = $connectionName;
                }
            }
        }

        if (empty($connectionsToDrop)) {
            $this->io->success("No migration changes detected.");

            return $this;
        }

        foreach ($connectionsToDrop as $connectionName) {
            $schemaCleaner->dropTables($connectionName);
        }

        foreach ($this->getConfigs() as $config) {
            $this->runMigrations($config);
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
     * @param  string[] $tables
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
     * @param  Migrations $migrations
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
     *
     * @param string[] $config Config array
     * @return string
     */
    protected function stringifyConfig(array $config): string
    {
        $options = [];
        foreach (['connection', 'plugin', 'source', 'target'] as $option) {
            if (isset($config[$option])) {
                $options[] = $option . ' "'.$config[$option].'"';
            }
        }

        return implode(', ', $options);
    }

    /**
     * @return array
     */
    public function getConfigs(): array
    {
        return $this->getConfigReader()->getConfig();
    }

    /**
     * @return ConfigReader
     */
    protected function getConfigReader(): ConfigReader
    {
        return $this->configReader;
    }
}
