<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\ConsoleOutput;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Core\Exception\MissingPluginException;
use Cake\Database\Exception\DatabaseException;
use Cake\TestSuite\TestCase;

class MigrateCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('Migrations.backend', 'builtin');

        $table = $this->fetchTable('Phinxlog');
        try {
            $table->deleteAll('1=1');
        } catch (DatabaseException $e) {
            //debug($e->getMessage());
        }
    }

    public function testHelp()
    {
        $this->exec('migrations migrate --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Apply migrations to a SQL datasource');
    }

    /**
     * Test that running with no migrations is successful
     *
     * @return void
     */
    public function testMigrateNoMigrationSource()
    {
        $this->exec('migrations migrate -c test -s Missing');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using paths</info> ' . ROOT . '/config/Missing');
        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(0, $table->find()->all()->toArray());
    }

    /**
     * Test that source parameter defaults to Migrations
     */
    public function testMigrateSourceDefault()
    {
        $this->exec('migrations migrate -c test');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('<info>using paths</info> ' . ROOT . '/config/Migrations');
        $this->assertOutputContains('MarkMigratedTest:</info> <comment>migrated');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(2, $table->find()->all()->toArray());
    }

    /**
     * Test that running with a no-op migrations is successful
     *
     * @return void
     */
    public function testMigrateWithSourceMigration()
    {
        $this->exec('migrations migrate -c test -s ShouldExecute');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('<info>using paths</info> ' . ROOT . '/config/ShouldExecute');
        $this->assertOutputContains('ShouldExecuteMigration:</info> <comment>migrated');
        $this->assertOutputContains('ShouldNotExecuteMigration:</info> <comment>skipped </comment>');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(1, $table->find()->all()->toArray());
    }

    /**
     * Test that migrations only run to a certain date
     */
    public function testMigrateDate()
    {
        $this->exec('migrations migrate -c test --date 2020-01-01');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('<info>using paths</info> ' . ROOT . '/config/Migrations');
        $this->assertOutputContains('MarkMigratedTest:</info> <comment>migrated');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(1, $table->find()->all()->toArray());
    }

    /**
     * Test output for dates with no matching migrations
     */
    public function testMigrateDateNotFound()
    {
        $this->exec('migrations migrate -c test --date 2000-01-01');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('<info>using paths</info> ' . ROOT . '/config/Migrations');
        $this->assertOutputNotContains('MarkMigratedTest');
        $this->assertOutputContains('No migrations to run');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(0, $table->find()->all()->toArray());
    }

    /**
     *
     * Test advancing migrations with an offset.
     */
    public function testMigrateTarget()
    {
        $this->exec('migrations migrate -c test --target 20150416223600');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('<info>using paths</info> ' . ROOT . '/config/Migrations');
        $this->assertOutputContains('MarkMigratedTest:</info> <comment>migrated');
        $this->assertOutputNotContains('MarkMigratedTestSecond');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(1, $table->find()->all()->toArray());
    }

    public function testMigrateTargetNotFound()
    {
        $this->exec('migrations migrate -c test --target 99');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('<info>using paths</info> ' . ROOT . '/config/Migrations');
        $this->assertOutputNotContains('MarkMigratedTest');
        $this->assertOutputNotContains('MarkMigratedTestSecond');
        $this->assertOutputContains('<comment>warning</comment> 99 is not a valid version');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(0, $table->find()->all()->toArray());
    }

    public function testMigrateFakeAll()
    {
        $this->exec('migrations migrate -c test --fake');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('<info>using paths</info> ' . ROOT . '/config/Migrations');
        $this->assertOutputContains('warning</warning> performing fake migrations');
        $this->assertOutputContains('MarkMigratedTest:</info> <comment>migrated');
        $this->assertOutputContains('MarkMigratedTestSecond:</info> <comment>migrated');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(2, $table->find()->all()->toArray());
    }

    public function testMigratePlugin()
    {
        $this->loadPlugins(['Migrator']);
        $this->exec('migrations migrate -c test --plugin Migrator');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('<info>using paths</info> ' . ROOT . '/Plugin/Migrator/config/Migrations');
        $this->assertOutputContains('Migrator:</info> <comment>migrated');
        $this->assertOutputContains('All Done');

        // Migration tracking table is plugin specific
        $table = $this->fetchTable('MigratorPhinxlog');
        $this->assertCount(1, $table->find()->all()->toArray());
    }

    public function testMigratePluginInvalid()
    {
        try {
            $this->exec('migrations migrate -c test --plugin NotThere');
            $this->fail('Should raise an error or exit with an error');
        } catch (MissingPluginException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test that migrating with the `--no-lock` option will not dispatch a dump shell
     *
     * @return void
     * /
    public function testMigrateWithNoLock()
    {
        $this->markTestIncomplete('not done here');
        $argv = [
            '-c',
            'test',
            '--no-lock',
        ];

        $this->command = $this->getMockCommand('MigrationsMigrateCommand');

        $this->command->expects($this->never())
            ->method('executeCommand');

        $this->command->run($argv, $this->getMockIo());
    }

    /**
     * Test that rolling back without the `--no-lock` option will dispatch a dump shell
     *
     * @return void
     * /
    public function testRollbackWithLock()
    {
        $this->markTestIncomplete('not done here');
        $argv = [
            '-c',
            'test',
        ];

        $this->command = $this->getMockCommand('MigrationsRollbackCommand');

        $this->command->expects($this->once())
            ->method('executeCommand');

        $this->command->run($argv, $this->getMockIo());
    }

    /**
     * Test that rolling back with the `--no-lock` option will not dispatch a dump shell
     *
     * @return void
     * /
    public function testRollbackWithNoLock()
    {
        $this->markTestIncomplete('not done here');
        $argv = [
            '-c',
            'test',
            '--no-lock',
        ];

        $this->command = $this->getMockCommand('MigrationsRollbackCommand');

        $this->command->expects($this->never())
            ->method('executeCommand');

        $this->command->run($argv, $this->getMockIo());
    }
    */
}
