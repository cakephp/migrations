<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Routing\Router;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase as BaseTestCase;
use Migrations\Db\Adapter\UnifiedMigrationsTableStorage;

abstract class TestCase extends BaseTestCase
{
    use ConsoleIntegrationTestTrait;
    use StringCompareTrait;

    /**
     * @var string
     */
    protected $generatedFile = '';

    /**
     * @var array
     */
    protected $generatedFiles = [];

    public function setUp(): void
    {
        parent::setUp();

        Router::reload();
        $this->loadPlugins(['Cake/TwigView']);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        if ($this->generatedFile && file_exists($this->generatedFile)) {
            unlink($this->generatedFile);
            $this->generatedFile = '';
        }

        if (count($this->generatedFiles)) {
            foreach ($this->generatedFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            $this->generatedFiles = [];
        }
    }

    /**
     * Load a plugin from the tests folder, and add to the autoloader
     *
     * @param string $name plugin name to load
     * @return void
     */
    protected function _loadTestPlugin($name)
    {
        $root = dirname(dirname(__FILE__)) . DS;
        $path = $root . 'test_app' . DS . 'Plugin' . DS . $name . DS;

        $this->loadPlugins([
            $name => [
                'path' => $path,
            ],
        ]);
    }

    /**
     * Assert that a list of files exist.
     *
     * @param array $files The list of files to check.
     * @param string $message The message to use if a check fails.
     * @return void
     */
    protected function assertFilesExist(array $files, $message = '')
    {
        foreach ($files as $file) {
            $this->assertFileExists($file, $message);
        }
    }

    /**
     * Assert that a file contains a substring
     *
     * @param string $expected The expected content.
     * @param string $path The path to check.
     * @param string $message The error message.
     * @return void
     */
    protected function assertFileContains($expected, $path, $message = '')
    {
        $this->assertFileExists($path, 'Cannot test contents, file does not exist.');

        $contents = file_get_contents($path);
        $this->assertStringContainsString($expected, $contents, $message);
    }

    /**
     * Assert that a file does not contain a substring
     *
     * @param string $expected The expected content.
     * @param string $path The path to check.
     * @param string $message The error message.
     * @return void
     */
    protected function assertFileNotContains($expected, $path, $message = '')
    {
        $this->assertFileExists($path, 'Cannot test contents, file does not exist.');

        $contents = file_get_contents($path);
        $this->assertStringNotContainsString($expected, $contents, $message);
    }

    /**
     * Check if using unified migrations table.
     *
     * @return bool
     */
    protected function isUsingUnifiedTable(): bool
    {
        return Configure::read('Migrations.legacyTables') === false;
    }

    /**
     * Get the migrations schema table name.
     *
     * @param string|null $plugin Plugin name
     * @return string
     */
    protected function getMigrationsTableName(?string $plugin = null): string
    {
        if ($this->isUsingUnifiedTable()) {
            return UnifiedMigrationsTableStorage::TABLE_NAME;
        }

        if ($plugin === null) {
            return 'phinxlog';
        }

        return strtolower($plugin) . '_phinxlog';
    }

    /**
     * Clear migration records from the schema table.
     *
     * @param string $connectionName Connection name
     * @param string|null $plugin Plugin name
     * @return void
     */
    protected function clearMigrationRecords(string $connectionName = 'test', ?string $plugin = null): void
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get($connectionName);
        $tableName = $this->getMigrationsTableName($plugin);

        $dialect = $connection->getDriver()->schemaDialect();
        if (!$dialect->hasTable($tableName)) {
            return;
        }

        if ($this->isUsingUnifiedTable()) {
            $query = $connection->deleteQuery()
                ->delete($tableName)
                ->where(['plugin IS' => $plugin]);
        } else {
            $query = $connection->deleteQuery()
                ->delete($tableName);
        }
        $query->execute();
    }

    /**
     * Get the count of migration records.
     *
     * @param string $connectionName Connection name
     * @param string|null $plugin Plugin name
     * @return int
     */
    protected function getMigrationRecordCount(string $connectionName = 'test', ?string $plugin = null): int
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get($connectionName);
        $tableName = $this->getMigrationsTableName($plugin);

        $dialect = $connection->getDriver()->schemaDialect();
        if (!$dialect->hasTable($tableName)) {
            return 0;
        }

        $query = $connection->selectQuery()
            ->select(['count' => $connection->selectQuery()->func()->count('*')])
            ->from($tableName);

        if ($this->isUsingUnifiedTable()) {
            $query->where(['plugin IS' => $plugin]);
        }

        $result = $query->execute()->fetch('assoc');

        return (int)($result['count'] ?? 0);
    }

    /**
     * Insert a migration record into the schema table.
     *
     * @param string $connectionName Connection name
     * @param int $version Version number
     * @param string $migrationName Migration name
     * @param string|null $plugin Plugin name
     * @return void
     */
    protected function insertMigrationRecord(
        string $connectionName,
        int $version,
        string $migrationName,
        ?string $plugin = null,
    ): void {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get($connectionName);
        $tableName = $this->getMigrationsTableName($plugin);

        $columns = ['version', 'migration_name', 'start_time', 'end_time', 'breakpoint'];
        $values = [
            'version' => $version,
            'migration_name' => $migrationName,
            'start_time' => '2024-01-01 00:00:00',
            'end_time' => '2024-01-01 00:00:01',
            'breakpoint' => 0,
        ];

        if ($this->isUsingUnifiedTable()) {
            $columns[] = 'plugin';
            $values['plugin'] = $plugin;
        }

        $connection->insertQuery()
            ->insert($columns)
            ->into($tableName)
            ->values($values)
            ->execute();
    }
}
