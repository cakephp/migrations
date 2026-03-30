<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Exception\MissingPluginException;
use Migrations\Test\TestCase\TestCase;
use RuntimeException;

class StatusCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearMigrationRecords('test');
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
        // Check for table name info
        $this->assertOutputContains('using migration table');
        // Check for headers
        $this->assertOutputContains('Status');
        $this->assertOutputContains('Migration ID');
        $this->assertOutputContains('Migration Name');
    }

    public function testExecuteSimpleJson(): void
    {
        $this->exec('migrations status -c test --format json');
        $this->assertExitSuccess();

        assert($this->_out instanceof StubConsoleOutput);
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
        // Run a migration first to ensure the schema table exists
        $this->exec('migrations migrate -c test --no-lock');
        $this->assertExitSuccess();

        // Insert a fake migration entry that doesn't exist in filesystem
        $this->insertMigrationRecord('test', 99999999999999, 'FakeMissingMigration');

        // Verify the fake migration is in the table
        $initialCount = $this->getMigrationRecordCount('test');
        $this->assertGreaterThan(0, $initialCount);

        // Run the clean command
        $this->exec('migrations status -c test --cleanup');
        $this->assertExitSuccess();
        $this->assertOutputContains('Removed 1 missing migration(s) from migration log.');
    }

    public function testCleanHelp(): void
    {
        $this->exec('migrations status --help');
        $this->assertExitSuccess();
        $this->assertOutputContains('--cleanup');
        $this->assertOutputContains('Remove MISSING migrations from the');
    }
}
