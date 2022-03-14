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

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\ConnectionHelper;
use Cake\TestSuite\TestCase;
use Migrations\TestSuite\Migrator;

class MigratorTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->restore = $GLOBALS['__PHPUNIT_BOOTSTRAP'];
        unset($GLOBALS['__PHPUNIT_BOOTSTRAP']);

        (new ConnectionHelper())->dropTables('test');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $GLOBALS['__PHPUNIT_BOOTSTRAP'] = $this->restore;

        (new ConnectionHelper())->dropTables('test');
    }

    public function testMigrateDropTruncate(): void
    {
        $migrator = new Migrator();
        $migrator->run(['plugin' => 'Migrator']);

        $connection = ConnectionManager::get('test');
        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains('migrator', $tables);

        $migrator->run(['plugin' => 'Migrator',]);

        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains('migrator', $tables);

        $this->assertCount(0, $connection->query('SELECT * FROM migrator')->fetchAll());
    }

    public function testMigrateDropNoTruncate(): void
    {
        $migrator = new Migrator();
        $migrator->run(['plugin' => 'Migrator'], false);

        $connection = ConnectionManager::get('test');
        $tables = $connection->getSchemaCollection()->listTables();

        $this->assertContains('migrator', $tables);
        $this->assertCount(1, $connection->query('SELECT * FROM migrator')->fetchAll());
    }

    public function testMigrateSkipTables(): void
    {
        $connection = ConnectionManager::get('test');

        // Create a table
        $connection->execute('CREATE TABLE skipme (name TEXT)');

        // Insert a record so that we can ensure the table was skipped.
        $connection->execute('INSERT INTO skipme (name) VALUES (:name)', ['name' => 'Ron']);

        $migrator = new Migrator();
        $migrator->run(['plugin' => 'Migrator', 'skip' => ['skip*']]);

        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains('migrator', $tables);
        $this->assertContains('skipme', $tables);
        $this->assertCount(1, $connection->query('SELECT * FROM skipme')->fetchAll());
    }

    public function testRunManyDropTruncate(): void
    {
        $migrator = new Migrator();
        $migrator->runMany([
            ['plugin' => 'Migrator',],
            ['plugin' => 'Migrator', 'source' => 'Migrations2',],
        ]);

        $connection = ConnectionManager::get('test');
        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains('migrator', $tables);
        $this->assertCount(0, $connection->query('SELECT * FROM migrator')->fetchAll());
        $this->assertCount(2, $connection->query('SELECT * FROM migrator_phinxlog')->fetchAll());
    }

    public function testRunManyDropNoTruncate(): void
    {
        $migrator = new Migrator();
        $migrator->runMany([
            ['plugin' => 'Migrator',],
            ['plugin' => 'Migrator', 'source' => 'Migrations2',],
        ], false);

        $connection = ConnectionManager::get('test');
        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains('migrator', $tables);
        $this->assertCount(2, $connection->query('SELECT * FROM migrator')->fetchAll());
        $this->assertCount(2, $connection->query('SELECT * FROM migrator_phinxlog')->fetchAll());
    }

    /**
     * @depends testMigrateDropNoTruncate
     */
    public function testTruncateAfterMigrations(): void
    {
        $this->testMigrateDropNoTruncate();

        $migrator = new Migrator();
        $migrator->truncate('test');

        $connection = ConnectionManager::get('test');
        $this->assertCount(0, $connection->query('SELECT * FROM migrator')->fetchAll());
    }
}
