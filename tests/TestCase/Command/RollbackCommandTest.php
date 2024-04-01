<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Cake\Database\Exception\DatabaseException;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class RollbackCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $createdFiles = [];

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('Migrations.backend', 'builtin');

        try {
            $table = $this->fetchTable('Phinxlog');
            $table->deleteAll('1=1');
        } catch (DatabaseException $e) {
        }

        try {
            $table = $this->fetchTable('MigratorPhinxlog');
            $table->deleteAll('1=1');
        } catch (DatabaseException $e) {
        }
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
        $this->_out = new StubConsoleOutput(STDOUT);
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

        $this->assertOutputContains('<info>using paths</info> ' . $migrationPath);
        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('No migrations to rollback');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(0, $table->find()->all()->toArray());

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
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

        $this->assertOutputContains('<info>using paths</info> ' . $migrationPath);
        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('dry-run mode enabled');
        $this->assertOutputContains('20240309223600 MarkMigratedTestSecond:</info> <comment>reverting');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(2, $table->find()->all()->toArray());

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
        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(2, $table->find()->all()->toArray());

        $this->exec('migrations rollback -c test --no-lock --target MarkMigratedTestSecond --fake');
        $this->assertExitSuccess();

        $this->assertOutputContains('performing fake rollbacks');
        $this->assertOutputContains('MarkMigratedTestSecond:</info> <comment>reverted');

        $this->assertCount(0, $table->find()->all()->toArray());

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

        $table = $this->fetchTable('Phinxlog');
        $this->assertEquals(0, $table->find()->count());
    }
}
