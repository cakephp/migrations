<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use InvalidArgumentException;
use Migrations\Test\TestCase\TestCase;
use ReflectionProperty;

class RollbackCommandTest extends TestCase
{
    protected array $createdFiles = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->clearMigrationRecords('test');
        $this->clearMigrationRecords('test', 'Migrator');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->createdFiles as $file) {
            unlink($file);
        }
    }

    protected function resetOutput(): void
    {
        if ($this->_out) {
            $property = new ReflectionProperty($this->_out, '_out');
            $property->setValue($this->_out, []);
        }
    }

    public function testHelp(): void
    {
        $this->exec('migrations migrate --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Apply migrations to a SQL datasource');
    }

    /**
     * Test that running with no migrations is successful
     */
    public function testSourceMissing(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Missing';
        $this->exec('migrations rollback -c test -s Missing --no-lock');
        $this->assertExitSuccess();

        $this->assertOutputContains('No migrations to rollback');
        $this->assertOutputContains('All Done');

        $this->assertEquals(0, $this->getMigrationRecordCount('test'));

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
    }

    public function testExecuteActuallyWorks(): void
    {
        $this->exec('migrations migrate -c test -s MigrationsRollback --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        $this->exec('migrations rollback -c test -s MigrationsRollback --no-lock');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>20250307183600 ChangeTestTable:</info> <comment>reverting </comment>');
        $this->assertOutputContains('<info>20250307183600 ChangeTestTable:</info> <comment>reverted');
        $this->resetOutput();

        $this->exec('migrations status -c test -s MigrationsRollback --format json');
        $this->assertExitSuccess();
        $output = $this->_out->messages();
        $parsed = json_decode($output[0], true);
        $this->assertEquals('down', $parsed[0]['status'], 'Migration status should be down');
    }

    /**
     * Test that running with dry-run works
     */
    public function testExecuteDryRun(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        $this->exec('migrations rollback -c test --no-lock --dry-run');
        $this->assertExitSuccess();

        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('20240309223600 MarkMigratedTestSecond:</info> <comment>reverting');
        $this->assertOutputContains('All Done');

        $count = $this->getMigrationRecordCount('test');
        $this->assertEquals(2, $count);

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
    }

    public function testDateOptionNoMigration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->exec('migrations rollback -c test --no-lock --date 2000-01-01');
    }

    public function testDateOptionInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->exec('migrations rollback -c test --no-lock --date 20001');
    }

    public function testDateOptionSuccessDateYearMonthDateHour(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        $this->exec('migrations rollback -c test --no-lock --date 2024030922');
        $this->assertExitSuccess();

        $this->assertOutputContains('MarkMigratedTestSecond:</info> <comment>reverted');

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
    }

    public function testDateOptionSuccessYearMonthDate(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        $this->exec('migrations rollback -c test --no-lock --date 20240309');
        $this->assertExitSuccess();

        $this->assertOutputContains('MarkMigratedTestSecond:</info> <comment>reverted');

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
    }

    public function testDateOptionSuccessYearMonth(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        $this->exec('migrations rollback -c test --no-lock --date 202403');
        $this->assertExitSuccess();

        $this->assertOutputContains('MarkMigratedTestSecond:</info> <comment>reverted');

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
    }

    public function testDateOptionSuccessYear(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        $this->exec('migrations rollback -c test --no-lock --date 2024');
        $this->assertExitSuccess();

        $this->assertOutputContains('MarkMigratedTestSecond:</info> <comment>reverted');

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
    }

    public function testTargetOption(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        $this->exec('migrations rollback -c test --no-lock --target MarkMigratedTestSecond');
        $this->assertExitSuccess();

        $this->assertOutputContains('MarkMigratedTestSecond:</info> <comment>reverted');

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
    }

    public function testPluginOption(): void
    {
        $this->loadPlugins(['Migrator']);
        $connection = ConnectionManager::get('test');
        $connection->execute('DROP TABLE IF EXISTS migrator');

        $this->exec('migrations migrate -c test --plugin Migrator --no-lock');
        $this->assertExitSuccess();

        // migration state was recorded.
        $this->assertEquals(1, $this->getMigrationRecordCount('test', 'Migrator'), 'migrate makes a row');
        // Table was created.
        $this->assertNotEmpty($this->fetchTable('Migrator')->getSchema());

        $this->resetOutput();

        $this->exec('migrations rollback -c test --plugin Migrator --no-lock');
        $this->assertExitSuccess();

        $this->assertOutputContains('Migrator:</info> <comment>reverted');
        // No more recorded migrations
        $this->assertEquals(0, $this->getMigrationRecordCount('test', 'Migrator'));
    }

    public function testLockOption(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();

        $this->exec('migrations rollback -c test --target MarkMigratedTestSecond');
        $this->assertExitSuccess();

        $this->assertOutputContains('MarkMigratedTestSecond:</info> <comment>reverted');

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->createdFiles[] = $dumpFile;
        $this->assertFileExists($dumpFile);
    }

    public function testFakeOption(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->resetOutput();
        $this->assertEquals(2, $this->getMigrationRecordCount('test'));

        $this->exec('migrations rollback -c test --no-lock --target MarkMigratedTestSecond --fake');
        $this->assertExitSuccess();

        $this->assertOutputContains('performing fake rollbacks');
        $this->assertOutputContains('MarkMigratedTestSecond:</info> <comment>reverted');

        $this->assertEquals(0, $this->getMigrationRecordCount('test'));

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
    }

    public function testEventsFired(): void
    {
        /** @var array<int, string> $fired */
        $fired = [];
        EventManager::instance()->on('Migration.beforeRollback', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });
        EventManager::instance()->on('Migration.afterRollback', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });
        $this->exec('migrations rollback -c test --no-lock');
        $this->assertExitSuccess();
        $this->assertSame(['Migration.beforeRollback', 'Migration.afterRollback'], $fired);
    }

    public function testBeforeMigrateEventAbort(): void
    {
        /** @var array<int, string> $fired */
        $fired = [];
        EventManager::instance()->on('Migration.beforeRollback', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
            $event->stopPropagation();
            $event->setResult(0);
        });
        EventManager::instance()->on('Migration.afterRollback', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });
        $this->exec('migrations rollback -c test --no-lock');
        $this->assertExitError();

        // Only one event was fired
        $this->assertSame(['Migration.beforeRollback'], $fired);

        $this->assertEquals(0, $this->getMigrationRecordCount('test'));
    }
}
