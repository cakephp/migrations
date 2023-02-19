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
namespace Migrations\Test\TestCase\TestSuite;

use Cake\Chronos\ChronosInterface;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\FrozenDate;
use Cake\TestSuite\ConnectionHelper;
use Cake\TestSuite\TestCase;
use Migrations\TestSuite\Migrator;
use RuntimeException;

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

    public function testRunManyMultipleSkip(): void
    {
        $migrator = new Migrator();
        // Run migrations the first time.
        $migrator->runMany([
            ['plugin' => 'Migrator'],
            ['plugin' => 'Migrator', 'source' => 'Migrations2'],
        ]);

        // Run migrations the second time. Skip clauses will cause problems.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not apply migrations');
        $migrator->runMany([
            ['plugin' => 'Migrator', 'skip' => ['migrator']],
            ['plugin' => 'Migrator', 'source' => 'Migrations2', 'skip' => ['m*']],
        ]);
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

    private function setMigrationEndDateToYesterday()
    {
        ConnectionManager::get('test')->newQuery()
            ->update('migrator_phinxlog')
            ->set('end_time', FrozenDate::yesterday(), 'timestamp')
            ->execute();
    }

    private function fetchMigrationEndDate(): ChronosInterface
    {
        $endTime = ConnectionManager::get('test')->newQuery()
            ->select('end_time')
            ->from('migrator_phinxlog')
            ->execute()->fetchColumn(0);

        return FrozenDate::parse($endTime);
    }

    public function testSkipMigrationDroppingIfOnlyUpMigrations(): void
    {
        // Run the migrator
        $migrator = new Migrator();
        $migrator->run(['plugin' => 'Migrator']);

        // Update the end time in the migrator_phinxlog table
        $this->setMigrationEndDateToYesterday();

        // Re-run the migrator
        $migrator->run(['plugin' => 'Migrator']);

        // Ensure that the end time is unchanged, meaning that the phinx table was not dropped
        // and the migrations were not re-run
        $this->assertTrue($this->fetchMigrationEndDate()->isYesterday());
    }

    public function testSkipMigrationDroppingIfOnlyUpMigrationsWithTwoSetsOfMigrations(): void
    {
        // Run the migrator
        $migrator = new Migrator();
        $migrator->runMany([
            ['plugin' => 'Migrator',],
            ['source' => '../../Plugin/Migrator/config/Migrations2',],
        ], false);

        // Update the end time in the migrator_phinxlog table
        $this->setMigrationEndDateToYesterday();

        // Re-run the migrator
        $migrator->runMany([
            ['plugin' => 'Migrator',],
            ['source' => '../../Plugin/Migrator/config/Migrations2',],
        ], false);

        // Ensure that the end time is unchanged, meaning that the phinx table was not dropped
        // and the migrations were not re-run
        $this->assertTrue($this->fetchMigrationEndDate()->isYesterday());
    }

    public function testDropMigrationsIfDownMigrations(): void
    {
        // Run the migrator
        $migrator = new Migrator();
        $migrator->run(['plugin' => 'Migrator']);

        // Update the end time in the migrator_phinxlog table
        $this->setMigrationEndDateToYesterday();

        // Re-run the migrator with additional down migrations
        $migrator->runMany([
            ['plugin' => 'Migrator',],
            ['plugin' => 'Migrator', 'source' => 'Migrations2',],
        ], false);

        // Ensure that the end time is today, meaning that the phinx table was truncated
        // and the migration were re-run
        $this->assertTrue($this->fetchMigrationEndDate()->isToday());
    }

    public function testDropMigrationsIfMissingMigrations(): void
    {
        // Run the migrator
        $migrator = new Migrator();
        $migrator->runMany([
            ['plugin' => 'Migrator',],
            ['plugin' => 'Migrator', 'source' => 'Migrations2',],
        ]);

        // Update the end time in the migrator_phinxlog table
        $this->setMigrationEndDateToYesterday();

        // Re-run the migrator with missing migrations
        $migrator->runMany([
            ['plugin' => 'Migrator',],
        ], false);

        // Ensure that the end time is today, meaning that the phinx table was truncated
        // and the migration were re-run
        $this->assertTrue($this->fetchMigrationEndDate()->isToday());
    }
}
