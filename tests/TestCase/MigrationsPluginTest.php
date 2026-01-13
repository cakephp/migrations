<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase;

use Cake\Console\CommandCollection;
use Cake\TestSuite\TestCase;
use Migrations\Command\RollbackCommand;
use Migrations\MigrationsPlugin;

class MigrationsPluginTest extends TestCase
{
    /**
     * Test that console() registers the correct RollbackCommand
     */
    public function testConsoleUsesCorrectRollbackCommand(): void
    {
        $plugin = new MigrationsPlugin();
        $commands = new CommandCollection();
        $commands = $plugin->console($commands);

        $this->assertTrue($commands->has('migrations rollback'));
        $this->assertSame(RollbackCommand::class, $commands->get('migrations rollback'));
    }
}
