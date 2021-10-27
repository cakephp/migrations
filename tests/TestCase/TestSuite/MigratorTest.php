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
namespace Migrations\Test\TestCase\TestSuite;

use Cake\Cache\Cache;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\TestSuite\Migrator;

class MigratorTest extends TestCase
{
    protected $dropDatabase = null;

    public function setUp(): void
    {
        $this->skipIf(!extension_loaded('pdo_sqlite'), 'Skipping as SQLite extension is missing');
        parent::setUp();

        $this->restore = $GLOBALS['__PHPUNIT_BOOTSTRAP'];
        unset($GLOBALS['__PHPUNIT_BOOTSTRAP']);

        $this->dropDatabase = tempnam(TMP, 'migrator_test_');
        ConnectionManager::setConfig('test_migrator', [
            'className' => Connection::class,
            'driver' => Sqlite::class,
            'database' => $this->dropDatabase,
        ]);
        Cache::clear('_cake_model_');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $GLOBALS['__PHPUNIT_BOOTSTRAP'] = $this->restore;

        ConnectionManager::drop('test_migrator');
        if (file_exists($this->dropDatabase)) {
            unlink($this->dropDatabase);
        }
    }

    public function testMigrateDropTruncate(): void
    {
        $migrator = new Migrator();
        $migrator->run(['connection' => 'test_migrator', 'source' => 'Migrator']);

        $connection = ConnectionManager::get('test_migrator');
        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains('migrator', $tables);

        $migrator->run(['connection' => 'test_migrator', 'source' => 'Migrator']);

        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains('migrator', $tables);

        $this->assertCount(0, $connection->query('SELECT * FROM migrator')->fetchAll());
    }

    public function testMigrateDropNoTruncate(): void
    {
        $migrator = new Migrator();
        $migrator->run(['connection' => 'test_migrator', 'source' => 'Migrator'], false);

        $connection = ConnectionManager::get('test_migrator');
        $tables = $connection->getSchemaCollection()->listTables();

        $this->assertContains('migrator', $tables);
        $this->assertCount(1, $connection->query('SELECT * FROM migrator')->fetchAll());
    }

    public function testRunManyDropTruncate(): void
    {
        $migrator = new Migrator();
        $migrator->runMany([
            ['connection' => 'test_migrator', 'source' => 'Migrator'],
            ['connection' => 'test_migrator', 'source' => 'Migrator2'],
        ]);

        $connection = ConnectionManager::get('test_migrator');
        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains('migrator', $tables);
        $this->assertCount(0, $connection->query('SELECT * FROM migrator')->fetchAll());
        $this->assertCount(2, $connection->query('SELECT * FROM phinxlog')->fetchAll());
    }

    public function testRunManyDropNoTruncate(): void
    {
        $migrator = new Migrator();
        $migrator->runMany([
            ['connection' => 'test_migrator', 'source' => 'Migrator'],
            ['connection' => 'test_migrator', 'source' => 'Migrator2'],
        ], false);

        $connection = ConnectionManager::get('test_migrator');
        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains('migrator', $tables);
        $this->assertCount(2, $connection->query('SELECT * FROM migrator')->fetchAll());
        $this->assertCount(2, $connection->query('SELECT * FROM phinxlog')->fetchAll());
    }

    /**
     * @depends testMigrateDropNoTruncate
     */
    public function testTruncateAfterMigrations(): void
    {
        $this->testMigrateDropNoTruncate();

        $migrator = new Migrator();
        $migrator->truncate('test_migrator');

        $connection = ConnectionManager::get('test_migrator');
        $this->assertCount(0, $connection->query('SELECT * FROM migrator')->fetchAll());
    }

    public function testTruncateExternalTables(): void
    {
        $connection = ConnectionManager::get('test_migrator');
        $connection->execute('CREATE TABLE external_table (colname TEXT NOT NULL);');
        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains('external_table', $tables);
        $connection->execute('INSERT INTO external_table (colname) VALUES ("test");');
        $this->assertCount(1, $connection->query('SELECT * FROM external_table')->fetchAll());

        $migrator = new Migrator();
        $migrator->truncate('test_migrator');

        $this->assertCount(0, $connection->query('SELECT * FROM external_table')->fetchAll());
    }
}
