<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
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

    public function testExecuteNoMigrations(): void
    {
        $this->exec('migrations status -c test');
        $this->assertExitSuccess();
        // Check for headers
        $this->assertOutputContains('Status');
        $this->assertOutputContains('Migration ID');
        $this->assertOutputContains('Migration Name');
    }
}
