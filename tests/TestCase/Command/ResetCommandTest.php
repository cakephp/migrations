<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Migrations\Test\TestCase\TestCase;
use ReflectionProperty;

class ResetCommandTest extends TestCase
{
    protected array $createdFiles = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->clearMigrationRecords('test');
        $this->clearMigrationRecords('test', 'Migrator');

        // Reset event manager to avoid pollution from other tests
        EventManager::instance()->off('Migration.beforeReset');
        EventManager::instance()->off('Migration.afterReset');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->createdFiles as $file) {
            unlink($file);
        }

        // Clean up event listeners
        EventManager::instance()->off('Migration.beforeReset');
        EventManager::instance()->off('Migration.afterReset');
    }

    protected function resetOutput(): void
    {
        if ($this->_out) {
            $property = new ReflectionProperty($this->_out, '_out');
            $property->setValue($this->_out, []);
        }
        $this->_in = null;
    }

    public function testHelp(): void
    {
        $this->exec('migrations reset --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Drop all tables and re-run all migrations');
        $this->assertOutputContains('destructive operation');
    }

    public function testDryRunWithTables(): void
    {
        // The test db has fixture tables, so there will be tables to drop
        $this->exec('migrations reset -c test --dry-run --no-lock');
        $this->assertExitSuccess();

        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('DRY-RUN: Would drop');
        $this->assertOutputContains('DRY-RUN: Would re-run all migrations');
    }

    public function testResetAborted(): void
    {
        // Test aborting the reset (fixture tables exist)
        $this->exec('migrations reset -c test --no-lock', ['n']);
        $this->assertExitSuccess();

        $this->assertOutputContains('The following tables will be dropped');
        $this->assertErrorContains('Reset operation aborted');
    }

    public function testResetConfirmed(): void
    {
        // First run migrations to create some tables
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        // Test confirming the reset
        $this->exec('migrations reset -c test --no-lock', ['y']);
        $this->assertExitSuccess();

        $this->assertOutputContains('The following tables will be dropped');
        $this->assertOutputContains('Dropped');
        $this->assertOutputContains('All Done');
    }

    public function testResetWithLock(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';

        // First run migrations
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        // Test reset with lock file generation
        $this->exec('migrations reset -c test', ['y']);
        $this->assertExitSuccess();

        $this->assertOutputContains('All Done');

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->createdFiles[] = $dumpFile;
        $this->assertFileExists($dumpFile);
    }

    public function testEventsFired(): void
    {
        // First create something to reset
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        // Clean up any existing listeners before registering test listeners
        EventManager::instance()->off('Migration.beforeReset');
        EventManager::instance()->off('Migration.afterReset');

        /** @var array<int, string> $fired */
        $fired = [];
        EventManager::instance()->on('Migration.beforeReset', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });
        EventManager::instance()->on('Migration.afterReset', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });

        $this->exec('migrations reset -c test --no-lock', ['y']);
        $this->assertExitSuccess();
        $this->assertSame(['Migration.beforeReset', 'Migration.afterReset'], $fired);
    }

    public function testBeforeResetEventAbort(): void
    {
        // First create something to reset
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        /** @var array<int, string> $fired */
        $fired = [];
        EventManager::instance()->on('Migration.beforeReset', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
            $event->stopPropagation();
            $event->setResult(0);
        });
        EventManager::instance()->on('Migration.afterReset', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });

        $this->exec('migrations reset -c test --no-lock', ['y']);
        $this->assertExitError();

        // Only one event was fired
        $this->assertSame(['Migration.beforeReset'], $fired);
    }

    public function testResetWithPlugin(): void
    {
        $this->loadPlugins(['Migrator']);
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $connection->execute('DROP TABLE IF EXISTS migrator');

        // Run plugin migrations
        $this->exec('migrations migrate -c test --plugin Migrator --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        // Reset with plugin option
        $this->exec('migrations reset -c test --plugin Migrator --no-lock', ['y']);
        $this->assertExitSuccess();

        $this->assertOutputContains('All Done');
    }
}
