<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Core\Exception\MissingPluginException;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Migrations\Test\TestCase\TestCase;

class MigrateCommandTest extends TestCase
{
    protected array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearMigrationRecords('test');
        $this->clearMigrationRecords('test', 'Migrator');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->createdFiles as $file) {
            unlink($file);
        }
        ConnectionManager::drop('invalid');
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
    public function testMigrateNoMigrationSource(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Missing';
        $this->exec('migrations migrate -c test -s Missing --no-lock');
        $this->assertExitSuccess();

        $this->assertOutputContains('All Done');

        $count = $this->getMigrationRecordCount('test');
        $this->assertEquals(0, $count);

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
    }

    /**
     * Test that source parameter defaults to Migrations
     */
    public function testMigrateInvalidConnection(): void
    {
        ConnectionManager::setConfig('invalid', [
            'database' => null,
        ]);
        $this->expectExceptionMessage('has no `database` key defined');
        $this->exec('migrations migrate -c invalid');
    }

    /**
     * Test that source parameter defaults to Migrations
     */
    public function testMigrateSourceDefault(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test');
        $this->assertExitSuccess();

        $this->assertOutputContains('MarkMigratedTest:</info> <comment>migrated');
        $this->assertOutputContains('All Done');

        $this->assertEquals(2, $this->getMigrationRecordCount('test'));

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->createdFiles[] = $dumpFile;
        $this->assertFileExists($dumpFile);
    }

    /**
     * Integration test for BaseMigration.
     */
    public function testMigrateBaseMigration(): void
    {
        $this->exec('migrations migrate -v --source BaseMigrations -c test --no-lock');
        $this->assertExitSuccess();

        $this->assertOutputContains('BaseMigrationTables:</info> <comment>migrated');
        $this->assertOutputContains('query=121');
        $this->assertOutputContains('fetchRow=122');
        $this->assertOutputContains('hasTable=1');
        $this->assertOutputContains('All Done');

        $this->assertEquals(1, $this->getMigrationRecordCount('test'));
    }

    /**
     * Test that running with a no-op migrations is successful
     */
    public function testMigrateWithSourceMigration(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'ShouldExecute';
        $this->exec('migrations migrate -c test -s ShouldExecute');
        $this->assertExitSuccess();

        $this->assertOutputContains('ShouldExecuteMigration:</info> <comment>migrated');
        $this->assertOutputContains('ShouldNotExecuteMigration:</info> <comment>skipped </comment>');
        $this->assertOutputContains('All Done');

        $this->assertEquals(1, $this->getMigrationRecordCount('test'));

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->createdFiles[] = $dumpFile;
        $this->assertFileExists($dumpFile);
    }

    /**
     * Test dry-run
     */
    public function testMigrateDryRun(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --dry-run');
        $this->assertExitSuccess();

        $this->assertOutputContains('DRY-RUN mode enabled');
        $this->assertOutputContains('MarkMigratedTest:</info> <comment>migrated');
        $this->assertOutputContains('All Done');

        $this->assertEquals(0, $this->getMigrationRecordCount('test'));

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->assertFileDoesNotExist($dumpFile);
    }

    /**
     * Test that migrations only run to a certain date
     */
    public function testMigrateDate(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --date 2020-01-01');
        $this->assertExitSuccess();

        $this->assertOutputContains('MarkMigratedTest:</info> <comment>migrated');
        $this->assertOutputContains('All Done');

        $this->assertEquals(1, $this->getMigrationRecordCount('test'));
        $this->assertFileExists($migrationPath . DS . 'schema-dump-test.lock');
    }

    /**
     * Test output for dates with no matching migrations
     */
    public function testMigrateDateNotFound(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --date 2000-01-01');
        $this->assertExitSuccess();

        $this->assertOutputNotContains('MarkMigratedTest');
        $this->assertOutputContains('No migrations to run');
        $this->assertOutputContains('All Done');

        $this->assertEquals(0, $this->getMigrationRecordCount('test'));
        $this->assertFileExists($migrationPath . DS . 'schema-dump-test.lock');
    }

    /**
     * Test advancing migrations with an offset.
     */
    public function testMigrateTarget(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --target 20150416223600');
        $this->assertExitSuccess();

        $this->assertOutputContains('MarkMigratedTest:</info> <comment>migrated');
        $this->assertOutputNotContains('MarkMigratedTestSecond');
        $this->assertOutputContains('All Done');

        $this->assertEquals(1, $this->getMigrationRecordCount('test'));

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->createdFiles[] = $dumpFile;
        $this->assertFileExists($dumpFile);
    }

    public function testMigrateTargetNotFound(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --target 99');
        $this->assertExitSuccess();

        $this->assertOutputNotContains('MarkMigratedTest');
        $this->assertOutputNotContains('MarkMigratedTestSecond');
        $this->assertOutputContains('<comment>warning</comment> 99 is not a valid version');
        $this->assertOutputContains('All Done');

        $this->assertEquals(0, $this->getMigrationRecordCount('test'));

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->createdFiles[] = $dumpFile;
        $this->assertFileExists($dumpFile);
    }

    public function testMigrateFakeAll(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --fake');
        $this->assertExitSuccess();

        $this->assertOutputContains('warning</warning> performing fake migrations');
        $this->assertOutputContains('MarkMigratedTest:</info> <comment>migrated');
        $this->assertOutputContains('MarkMigratedTestSecond:</info> <comment>migrated');
        $this->assertOutputContains('All Done');

        $this->assertEquals(2, $this->getMigrationRecordCount('test'));

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->createdFiles[] = $dumpFile;
        $this->assertFileExists($dumpFile);
    }

    public function testMigratePlugin(): void
    {
        $this->loadPlugins(['Migrator']);
        $migrationPath = ROOT . DS . 'Plugin' . DS . 'Migrator' . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --plugin Migrator');
        $this->assertExitSuccess();

        $this->assertOutputContains('Migrator:</info> <comment>migrated');
        $this->assertOutputContains('All Done');

        // Migration tracking table is plugin specific
        $this->assertEquals(1, $this->getMigrationRecordCount('test', 'Migrator'));

        $dumpFile = $migrationPath . DS . 'schema-dump-test.lock';
        $this->createdFiles[] = $dumpFile;
        $this->assertFileExists($dumpFile);
    }

    public function testMigratePluginInvalid(): void
    {
        try {
            $this->exec('migrations migrate -c test --plugin NotThere');
            $this->fail('Should raise an error or exit with an error');
        } catch (MissingPluginException) {
            $this->assertTrue(true);
        }

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertNotContains('not_there_phinxlog', $tables);
    }

    /**
     * Test that migrating with the `--no-lock` option will not dispatch a dump shell
     *
     * @return void
     */
    public function testMigrateWithNoLock(): void
    {
        $migrationPath = ROOT . DS . 'config' . DS . 'Migrations';
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();

        $this->assertOutputContains('MarkMigratedTest:</info> <comment>migrated');
        $this->assertOutputContains('All Done');
        $this->assertOutputNotContains('Dumping');
        $this->assertFileDoesNotExist($migrationPath . DS . 'schema-dump-test.lock');
    }

    public function testEventsFired(): void
    {
        /** @var array<int, string> $fired */
        $fired = [];
        EventManager::instance()->on('Migration.beforeMigrate', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });
        EventManager::instance()->on('Migration.afterMigrate', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();
        $this->assertSame(['Migration.beforeMigrate', 'Migration.afterMigrate'], $fired);
    }

    public function testBeforeMigrateEventAbort(): void
    {
        /** @var array<int, string> $fired */
        $fired = [];
        EventManager::instance()->on('Migration.beforeMigrate', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
            $event->stopPropagation();
            $event->setResult(0);
        });
        EventManager::instance()->on('Migration.afterMigrate', function (EventInterface $event) use (&$fired): void {
            $fired[] = $event->getName();
        });
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitError();

        // Only one event was fired
        $this->assertSame(['Migration.beforeMigrate'], $fired);

        $this->assertEquals(0, $this->getMigrationRecordCount('test'));
    }
}
