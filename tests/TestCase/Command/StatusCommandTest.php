<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Core\Exception\MissingPluginException;
use Cake\Database\Exception\DatabaseException;
use Cake\TestSuite\TestCase;
use RuntimeException;

class StatusCommandTest extends TestCase
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
        }
    }

    public function testHelp(): void
    {
        $this->exec('migrations status --help');
        $this->assertExitSuccess();
        $this->assertOutputContains('command prints a list of all migrations');
        $this->assertOutputContains('migrations status -c secondary');
    }

    public function testExecuteSimple(): void
    {
        $this->exec('migrations status -c test');
        $this->assertExitSuccess();
        // Check for headers
        $this->assertOutputContains('Status');
        $this->assertOutputContains('Migration ID');
        $this->assertOutputContains('Migration Name');
    }

    public function testExecuteSimpleJson(): void
    {
        $this->exec('migrations status -c test --format json');
        $this->assertExitSuccess();

        assert(isset($this->_out));
        $output = $this->_out->messages();
        $parsed = json_decode($output[0], true);
        $this->assertTrue(is_array($parsed));
        $this->assertCount(2, $parsed);
        $this->assertArrayHasKey('id', $parsed[0]);
        $this->assertArrayHasKey('status', $parsed[0]);
        $this->assertArrayHasKey('name', $parsed[0]);
    }

    public function testExecutePlugin(): void
    {
        $this->loadPlugins(['Migrator']);
        $this->exec('migrations status -c test -p Migrator');
        $this->assertExitSuccess();
        $this->assertOutputRegExp("/\|.*?down.*\|.*?Migrator.*?\|/");
    }

    public function testExecutePluginDoesNotExist(): void
    {
        $this->expectException(MissingPluginException::class);
        $this->exec('migrations status -c test -p LolNope');
    }

    public function testExecuteConnectionDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->exec('migrations status -c lolnope');
    }

    public function testCleanNoMissingMigrations(): void
    {
        $this->exec('migrations status -c test --cleanup');
        $this->assertExitSuccess();
        $this->assertOutputContains('No missing migrations to clean up.');
    }

    public function testCleanWithMissingMigrations(): void
    {
        // First, insert a fake migration entry that doesn't exist in filesystem
        $table = $this->fetchTable('Phinxlog');
        $entity = $table->newEntity([
            'version' => 99999999999999,
            'migration_name' => 'FakeMissingMigration',
            'start_time' => '2024-01-01 00:00:00',
            'end_time' => '2024-01-01 00:00:01',
            'breakpoint' => false,
        ]);
        $table->save($entity);

        // Verify the fake migration is in the table
        $count = $table->find()->where(['version' => 99999999999999])->count();
        $this->assertEquals(1, $count);

        // Run the clean command
        $this->exec('migrations status -c test --cleanup');
        $this->assertExitSuccess();
        $this->assertOutputContains('Removed 1 missing migration(s) from migration log.');

        // Verify the fake migration was removed
        $count = $table->find()->where(['version' => 99999999999999])->count();
        $this->assertEquals(0, $count);
    }

    public function testCleanHelp(): void
    {
        $this->exec('migrations status --help');
        $this->assertExitSuccess();
        $this->assertOutputContains('--cleanup');
        $this->assertOutputContains('Remove MISSING migrations from the phinxlog table');
    }
}
