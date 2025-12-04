<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Adapter;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Exception;
use Migrations\Db\Adapter\UnifiedMigrationsTableStorage;
use Migrations\Test\TestCase\TestCase;

/**
 * Tests for UnifiedMigrationsTableStorage.
 *
 * These tests verify the unified table storage operations work correctly
 * when LEGACY_TABLES=false is configured.
 *
 * Note: Integration tests that exercise the full storage functionality
 * are covered by the existing command tests (MigrateCommandTest, etc.)
 * when run with LEGACY_TABLES=false environment variable.
 */
class UnifiedMigrationsTableStorageTest extends TestCase
{
    /**
     * @var bool
     */
    private bool $tableCreated = false;

    public function setUp(): void
    {
        parent::setUp();

        // Force unified table mode for these tests
        Configure::write('Migrations.legacyTables', false);

        // Clean up any existing table from previous test runs
        $this->cleanupTable();
    }

    public function tearDown(): void
    {
        // Always clean up the table
        $this->cleanupTable();

        Configure::delete('Migrations.legacyTables');

        parent::tearDown();
    }

    /**
     * Clean up the unified migrations table and other test artifacts.
     */
    private function cleanupTable(): void
    {
        try {
            /** @var \Cake\Database\Connection $connection */
            $connection = ConnectionManager::get('test');
            $driver = $connection->getDriver();

            // Drop unified migrations table
            $connection->execute(sprintf(
                'DROP TABLE IF EXISTS %s',
                $driver->quoteIdentifier(UnifiedMigrationsTableStorage::TABLE_NAME),
            ));

            // Drop tables created by test migrations
            $connection->execute('DROP TABLE IF EXISTS migrator');
            $connection->execute('DROP TABLE IF EXISTS numbers');
            $connection->execute('DROP TABLE IF EXISTS letters');
            $connection->execute('DROP TABLE IF EXISTS stores');
            $connection->execute('DROP TABLE IF EXISTS mark_migrated');
            $connection->execute('DROP TABLE IF EXISTS mark_migrated_test');

            // Also drop any phinxlog tables that might exist
            $connection->execute('DROP TABLE IF EXISTS phinxlog');
            $connection->execute('DROP TABLE IF EXISTS migrator_phinxlog');
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }

    public function testTableName(): void
    {
        $this->assertSame('cake_migrations', UnifiedMigrationsTableStorage::TABLE_NAME);
    }

    public function testMigrateCreatesUnifiedTable(): void
    {
        // Run a migration which should create the unified table
        $this->exec('migrations migrate -c test --source Migrations --no-lock');
        $this->assertExitSuccess();

        // Verify unified table was created
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $dialect = $connection->getDriver()->schemaDialect();

        $this->assertTrue($dialect->hasTable(UnifiedMigrationsTableStorage::TABLE_NAME));
        $this->tableCreated = true;

        // Verify records were inserted with null plugin (app migrations)
        $result = $connection->selectQuery()
            ->select('*')
            ->from(UnifiedMigrationsTableStorage::TABLE_NAME)
            ->execute()
            ->fetchAll('assoc');

        $this->assertGreaterThan(0, count($result));

        // All records should have null plugin (app migrations)
        foreach ($result as $row) {
            $this->assertNull($row['plugin']);
        }
    }

    public function testMigratePluginUsesUnifiedTable(): void
    {
        $this->loadPlugins(['Migrator']);

        // Run app migrations first to create the table
        $this->exec('migrations migrate -c test --source Migrations --no-lock');
        $this->assertExitSuccess();
        $this->tableCreated = true;

        // Clear the migration records for app (but keep the table)
        $this->clearMigrationRecords('test');

        // Run plugin migrations
        $this->exec('migrations migrate -c test --plugin Migrator --no-lock');
        $this->assertExitSuccess();

        // Verify plugin records were inserted with plugin name
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $result = $connection->selectQuery()
            ->select('*')
            ->from(UnifiedMigrationsTableStorage::TABLE_NAME)
            ->where(['plugin' => 'Migrator'])
            ->execute()
            ->fetchAll('assoc');

        $this->assertGreaterThan(0, count($result));
        $this->assertEquals('Migrator', $result[0]['plugin']);
    }

    public function testRollbackWithUnifiedTable(): void
    {
        // Run migrations
        $this->exec('migrations migrate -c test --source Migrations --no-lock');
        $this->assertExitSuccess();
        $this->tableCreated = true;

        // Verify we have records
        $initialCount = $this->getMigrationRecordCount('test');
        $this->assertGreaterThan(0, $initialCount);

        // Rollback
        $this->exec('migrations rollback -c test --source Migrations --no-lock');
        $this->assertExitSuccess();

        // Verify record was removed
        $afterCount = $this->getMigrationRecordCount('test');
        $this->assertLessThan($initialCount, $afterCount);
    }

    public function testStatusWithUnifiedTable(): void
    {
        // Run migrations
        $this->exec('migrations migrate -c test --source Migrations --no-lock');
        $this->assertExitSuccess();
        $this->tableCreated = true;

        // Check status
        $this->exec('migrations status -c test --source Migrations');
        $this->assertExitSuccess();
        $this->assertOutputContains('up');
    }

    public function testAppAndPluginMigrationsAreSeparated(): void
    {
        $this->loadPlugins(['Migrator']);

        // Run app migrations
        $this->exec('migrations migrate -c test --source Migrations --no-lock');
        $this->assertExitSuccess();
        $this->tableCreated = true;

        // Run plugin migrations
        $this->exec('migrations migrate -c test --plugin Migrator --no-lock');
        $this->assertExitSuccess();

        // Verify both app and plugin records exist in same table but are separated
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        // App records (plugin IS NULL)
        $appCount = $connection->selectQuery()
            ->select(['count' => $connection->selectQuery()->func()->count('*')])
            ->from(UnifiedMigrationsTableStorage::TABLE_NAME)
            ->where(['plugin IS' => null])
            ->execute()
            ->fetch('assoc');

        // Plugin records
        $pluginCount = $connection->selectQuery()
            ->select(['count' => $connection->selectQuery()->func()->count('*')])
            ->from(UnifiedMigrationsTableStorage::TABLE_NAME)
            ->where(['plugin' => 'Migrator'])
            ->execute()
            ->fetch('assoc');

        $this->assertGreaterThan(0, (int)$appCount['count'], 'App migrations should exist');
        $this->assertGreaterThan(0, (int)$pluginCount['count'], 'Plugin migrations should exist');

        // Rolling back app shouldn't affect plugin
        $this->exec('migrations rollback -c test --source Migrations --target 0 --no-lock');
        $this->assertExitSuccess();

        // Plugin migrations should still exist
        $pluginCountAfter = $connection->selectQuery()
            ->select(['count' => $connection->selectQuery()->func()->count('*')])
            ->from(UnifiedMigrationsTableStorage::TABLE_NAME)
            ->where(['plugin' => 'Migrator'])
            ->execute()
            ->fetch('assoc');

        $this->assertEquals($pluginCount['count'], $pluginCountAfter['count'], 'Plugin migrations should be unaffected');
    }
}
