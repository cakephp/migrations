<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Exception;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Migration\Environment;
use Migrations\Test\TestCase\TestCase;

class UpgradeCommandTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->clearMigrationRecords('test');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $connection->execute('DROP TABLE IF EXISTS cake_migrations');
    }

    protected function getAdapter(): AdapterInterface
    {
        $config = ConnectionManager::getConfig('test');
        $environment = new Environment('default', [
            'connection' => 'test',
            'database' => $config['database'],
            'migration_table' => 'phinxlog',
        ]);

        return $environment->getAdapter();
    }

    public function testHelp(): void
    {
        Configure::write('Migrations.legacyTables', null);

        $this->exec('migrations upgrade --help');
        $this->assertExitSuccess();
        $this->assertOutputContains('Upgrades migration tracking');
        $this->assertOutputContains('migrations upgrade --dry-run');
    }

    public function testExecuteSimpleDryRun(): void
    {
        Configure::write('Migrations.legacyTables', true);
        try {
            $this->getAdapter()->createSchemaTable();
        } catch (Exception $e) {
            // Table probably exists
        }

        $this->exec('migrations upgrade -c test --dry-run');
        $this->assertExitSuccess();
        // Check for status output
        $this->assertOutputContains('DRY RUN');
        $this->assertOutputContains('Creating unified table');
        $this->assertOutputContains('Total records migrated');
    }

    public function testExecuteSimpleExecute(): void
    {
        Configure::write('Migrations.legacyTables', true);
        $config = ConnectionManager::getConfig('test');
        $environment = new Environment('default', [
            'connection' => 'test',
            'database' => $config['database'],
            'migration_table' => 'phinxlog',
        ]);
        $adapter = $environment->getAdapter();
        try {
            $adapter->createSchemaTable();
        } catch (Exception $e) {
            // Table probably exists
        }

        $this->exec('migrations upgrade -c test');
        $this->assertExitSuccess();

        // No dry run and drop table output is present.
        $this->assertOutputNotContains('DRY RUN');
        $this->assertOutputContains('Creating unified table');
        $this->assertOutputContains('Total records migrated');

        $this->assertTrue($adapter->hasTable('cake_migrations'));
        $this->assertTrue($adapter->hasTable('phinxlog'));
    }

    public function testExecuteSimpleExecuteDropTables(): void
    {
        Configure::write('Migrations.legacyTables', true);
        $config = ConnectionManager::getConfig('test');
        $environment = new Environment('default', [
            'connection' => 'test',
            'database' => $config['database'],
            'migration_table' => 'phinxlog',
        ]);
        $adapter = $environment->getAdapter();
        try {
            $adapter->createSchemaTable();
        } catch (Exception $e) {
            // Table probably exists
        }

        $this->exec('migrations upgrade -c test --drop-tables');
        $this->assertExitSuccess();

        // Check for status output
        $this->assertOutputNotContains('DRY RUN');
        $this->assertOutputContains('Creating unified table');
        $this->assertOutputContains('Dropping legacy table');
        $this->assertOutputContains('Total records migrated');

        $this->assertTrue($adapter->hasTable('cake_migrations'));
        $this->assertFalse($adapter->hasTable('phinxlog'));
    }
}
