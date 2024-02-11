<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Core\Exception\MissingPluginException;
use Cake\TestSuite\TestCase;

class StatusCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('Migrations.backend', 'builtin');
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
        $this->assertCount(1, $parsed);
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
        $this->exec('migrations status -c lolnope');
        $this->assertExitError();
    }
}
