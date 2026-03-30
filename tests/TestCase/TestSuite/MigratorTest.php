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

use Cake\Chronos\ChronosDate;
use Cake\Core\Configure;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\ConnectionHelper;
use Cake\TestSuite\TestCase;
use Migrations\Db\Adapter\UnifiedMigrationsTableStorage;
use Migrations\TestSuite\Migrator;
use PHPUnit\Framework\Attributes\Depends;
use RuntimeException;

class MigratorTest extends TestCase
{
    /**
     * @var string
     */
    protected $restore;

    /**
     * Get the migration table name for the Migrator plugin.
     *
     * @return string
     */
    protected function getMigratorTableName(): string
    {
        return Configure::read('Migrations.legacyTables') === false
            ? UnifiedMigrationsTableStorage::TABLE_NAME
            : 'migrator_phinxlog';
    }

    /**
     * Build a WHERE clause for filtering by plugin in unified mode.
     *
     * @return array
     */
    protected function getMigratorWhereClause(): array
    {
        return Configure::read('Migrations.legacyTables') === false
            ? ['plugin' => 'Migrator']
            : [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (isset($GLOBALS['__PHPUNIT_BOOTSTRAP'])) {
            $this->restore = $GLOBALS['__PHPUNIT_BOOTSTRAP'];
            unset($GLOBALS['__PHPUNIT_BOOTSTRAP']);
        }

        (new ConnectionHelper())->dropTables('test');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->restore) {
            $GLOBALS['__PHPUNIT_BOOTSTRAP'] = $this->restore;
            unset($this->restore);
        }

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

        $this->assertCount(0, $connection->selectQuery()->select(['*'])->from('migrator')->execute()->fetchAll());
    }

    public function testMigrateDropNoTruncate(): void
    {
        $migrator = new Migrator();
        $migrator->run(['plugin' => 'Migrator'], false);

        $connection = ConnectionManager::get('test');
        $tables = $connection->getSchemaCollection()->listTables();

        $this->assertContains('migrator', $tables);
        $this->assertCount(1, $connection->selectQuery()->select(['*'])->from('migrator')->execute()->fetchAll());
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
        $this->assertCount(1, $connection->selectQuery()->select(['*'])->from('skipme')->execute()->fetchAll());
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
        $this->assertCount(0, $connection->selectQuery()->select(['*'])->from('migrator')->execute()->fetchAll());
        $query = $connection->selectQuery()->select(['*'])->from($this->getMigratorTableName());
        $where = $this->getMigratorWhereClause();
        if ($where) {
            $query->where($where);
        }
        $this->assertCount(2, $query->execute()->fetchAll());
    }

    public function testRunManyMultipleSkip(): void
    {
        $connection = ConnectionManager::get('test');
        $this->skipIf($connection->getDriver() instanceof Postgres);
        // Skip for unified mode - migration history detection works differently
        $this->skipIf(Configure::read('Migrations.legacyTables') === false);

        $migrator = new Migrator();
        // Run migrations for the first time.
        $migrator->runMany([
            ['plugin' => 'Migrator'],
            ['plugin' => 'Migrator', 'source' => 'Migrations2'],
        ]);

        // Run migrations the second time. Skip clauses will cause problems.
        try {
            $migrator->runMany([
                ['plugin' => 'Migrator', 'skip' => ['migrator']],
                ['plugin' => 'Migrator', 'source' => 'Migrations2', 'skip' => ['m*']],
            ]);
            $this->fail('Should fail because of table drops');
        } catch (RuntimeException $e) {
            $connection->getDriver()->disconnect();
            $this->assertStringContainsString('Could not apply migrations', $e->getMessage());
        }
    }

    #[Depends('testMigrateDropNoTruncate')]
    public function testTruncateAfterMigrations(): void
    {
        $this->testMigrateDropNoTruncate();

        $migrator = new Migrator();
        $migrator->truncate('test');

        $connection = ConnectionManager::get('test');
        $this->assertCount(0, $connection->selectQuery()->select(['*'])->from('migrator')->execute()->fetchAll());
    }

    private function setMigrationEndDateToYesterday(): void
    {
        $query = ConnectionManager::get('test')->updateQuery()
            ->update($this->getMigratorTableName())
            ->set('end_time', ChronosDate::yesterday(), 'timestamp');
        $where = $this->getMigratorWhereClause();
        if ($where) {
            $query->where($where);
        }
        $query->execute();
    }

    private function fetchMigrationEndDate(): ChronosDate
    {
        $query = ConnectionManager::get('test')->selectQuery()
            ->select('end_time')
            ->from($this->getMigratorTableName());
        $where = $this->getMigratorWhereClause();
        if ($where) {
            $query->where($where);
        }
        $endTime = $query->execute()->fetchColumn(0);

        if (!$endTime || is_bool($endTime)) {
            $this->markTestSkipped('Cannot read end_time, bailing.');
        }

        return ChronosDate::parse($endTime);
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
        // Skip for unified mode - migration history detection works differently
        $this->skipIf(Configure::read('Migrations.legacyTables') === false);

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
        // Skip for unified mode - migration history detection works differently
        $this->skipIf(Configure::read('Migrations.legacyTables') === false);

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
