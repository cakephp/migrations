<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\ConsoleOutput;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

class MigrateCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('Migrations.backend', 'builtin');
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
    public function testMigrateNoMigrationsSuccess()
    {
        $this->exec('migrations migrate -c test -s Missing');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(0, $table->find()->all()->toArray());
    }

    /**
     * Test that running with a no-op migrations is successful
     *
     * @return void
     */
    public function testMigrateWithSourceMigration()
    {
        $this->exec('migrations migrate -c test -s Migrations');
        $this->assertExitSuccess();

        $this->assertOutputContains('<info>using connection</info> test');
        $this->assertOutputContains('Running <info>MarkMigratedTest</info>');
        $this->assertOutputContains('All Done');

        $table = $this->fetchTable('Phinxlog');
        $this->assertCount(1, $table->find()->all()->toArray());
    }

    /**
     * Test that migrating with the `--no-lock` option will not dispatch a dump shell
     *
     * @return void
     */
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
     */
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
     */
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

    public function testMigrate()
    {
        $this->markTestIncomplete('not done here');
    }

    public function testMigrateSource()
    {
        $this->markTestIncomplete('not done here');
    }

    public function testMigrateSourceInvalid()
    {
        $this->markTestIncomplete('not done here');
    }

    public function testMigrateDate()
    {
        $this->markTestIncomplete('not done here');
    }

    public function testMigrateDateNotFound()
    {
        $this->markTestIncomplete('not done here');
    }

    public function testMigrateTarget()
    {
        $this->markTestIncomplete('not done here');
    }

    public function testMigrateTargetNotFound()
    {
        $this->markTestIncomplete('not done here');
    }

    public function testMigrateFake()
    {
        $this->markTestIncomplete('not done here');
    }

    public function testMigratePlugin()
    {
        $this->markTestIncomplete('not done here');
    }

    public function testMigratePluginInvalid()
    {
        $this->markTestIncomplete('not done here');
    }
}
