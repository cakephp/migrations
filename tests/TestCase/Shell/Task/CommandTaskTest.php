<?php
/**
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase\Shell\Task;

use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Migrations\Test\TestCase\Shell\TestCompletionStringOutput;

/**
 * Class CommandTaskTest
 */
class CommandTaskTest extends TestCase
{

    /**
     * Instance of ConsoleOutput
     *
     * @var \Cake\Console\ConsoleOutput
     */
    public $out;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        $this->skipIf(version_compare(Configure::version(), '3.1.6', '<'));

        parent::setUp();

        $this->out = new TestCompletionStringOutput();
        $io = new ConsoleIo($this->out);

        $this->Shell = $this->getMock(
            'Cake\Shell\CompletionShell',
            ['in', '_stop', 'clear'],
            [$io]
        );

        $this->Shell->Command = $this->getMock(
            'Cake\Shell\Task\CommandTask',
            ['in', '_stop', 'clear'],
            [$io]
        );
    }

    /**
     * tearDown
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        unset($this->Shell);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsSubcommands()
    {
        $this->Shell->runCommand(['subcommands', 'Migrations.migrations']);
        $output = $this->out->output;
        $expected = "create mark_migrated migrate rollback status\n";
        $this->assertTextEquals($expected, $output);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsOptionsCreate()
    {
        $this->Shell->runCommand(['options', 'Migrations.migrations', 'create']);
        $output = $this->out->output;
        $expected = "--help -h --verbose -v --quiet -q --plugin -p --connection -c --source -s --ansi --no-ansi";
        $expected .= " --no-interaction -n --template -t --class -l\n";
        $this->assertTextEquals($expected, $output);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsOptionsMarkMigrated()
    {
        $this->Shell->runCommand(['options', 'Migrations.migrations', 'mark_migrated']);
        $output = $this->out->output;
        $expected = "--help -h --verbose -v --quiet -q --plugin -p --connection -c --source -s --ansi --no-ansi";
        $expected .= " --no-interaction -n --exclude -x --only -o\n";
        $this->assertTextEquals($expected, $output);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsOptionsMigrate()
    {
        $this->Shell->runCommand(['options', 'Migrations.migrations', 'migrate']);
        $output = $this->out->output;
        $expected = "--help -h --verbose -v --quiet -q --plugin -p --connection -c --source -s --ansi --no-ansi";
        $expected .= " --no-interaction -n --target -t --date -d\n";
        $this->assertTextEquals($expected, $output);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsOptionsRollback()
    {
        $this->Shell->runCommand(['options', 'Migrations.migrations', 'rollback']);
        $output = $this->out->output;
        $expected = "--help -h --verbose -v --quiet -q --plugin -p --connection -c --source -s --ansi --no-ansi";
        $expected .= " --no-interaction -n --target -t --date -d\n";
        $this->assertTextEquals($expected, $output);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsOptionsStatus()
    {
        $this->Shell->runCommand(['options', 'Migrations.migrations', 'status']);
        $output = $this->out->output;
        $expected = "--help -h --verbose -v --quiet -q --plugin -p --connection -c --source -s --ansi --no-ansi";
        $expected .= " --no-interaction -n --format -f\n";
        $this->assertTextEquals($expected, $output);
    }
}
