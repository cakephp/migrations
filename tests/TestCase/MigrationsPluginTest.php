<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase;

use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Migrations\Command\MigrationsRollbackCommand;
use Migrations\Command\RollbackCommand;
use Migrations\MigrationsPlugin;

class MigrationsPluginTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Configure::delete('Migrations.backend');
    }

    /**
     * Test that builtin backend uses RollbackCommand
     */
    public function testConsoleBuiltinBackendUsesCorrectRollbackCommand(): void
    {
        Configure::write('Migrations.backend', 'builtin');

        $plugin = new MigrationsPlugin();
        $commands = new CommandCollection();
        $commands = $plugin->console($commands);

        $this->assertTrue($commands->has('migrations rollback'));
        $this->assertSame(RollbackCommand::class, $commands->get('migrations rollback'));
    }

    /**
     * Test that phinx backend uses MigrationsRollbackCommand
     *
     * This is the reported bug in https://github.com/cakephp/migrations/issues/990
     */
    public function testConsolePhinxBackendUsesCorrectRollbackCommand(): void
    {
        Configure::write('Migrations.backend', 'phinx');

        $plugin = new MigrationsPlugin();
        $commands = new CommandCollection();
        $commands = $plugin->console($commands);

        $this->assertTrue($commands->has('migrations rollback'));
        // Bug: RollbackCommand is loaded instead of MigrationsRollbackCommand
        $this->assertSame(MigrationsRollbackCommand::class, $commands->get('migrations rollback'));
    }
}
