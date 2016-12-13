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


        $this->Shell = $this->getMockBuilder('\Cake\Shell\CompletionShell')
            ->setMethods(['in', '_stop', 'clear'])
            ->setConstructorArgs([$io])
            ->getMock();

        $this->Shell->Command = $this->getMockBuilder('\Cake\Shell\Task\CommandTask')
            ->setMethods(['in', '_stop', 'clear'])
            ->setConstructorArgs([$io])
            ->getMock();
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
        $expected = "create dump mark_migrated migrate rollback status\n";
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
        $this->skipIf(version_compare(phpversion(), '5.5.0', '<'));
        $this->Shell->runCommand(['options', 'Migrations.migrations', 'create']);
        $output = $this->out->output;
        $expected = "--ansi --help -h --no-ansi --no-interaction -n --quiet -q --verbose -v --class -l --connection";
        $expected .= " -c --plugin -p --source -s --template -t\n";
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
        $this->skipIf(version_compare(phpversion(), '5.5.0', '<'));
        $this->Shell->runCommand(['options', 'Migrations.migrations', 'mark_migrated']);
        $output = $this->out->output;
        $expected = "--ansi --help -h --no-ansi --no-interaction -n --quiet -q --verbose -v --connection -c";
        $expected .= " --exclude -x --only -o --plugin -p --source -s\n";
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
        $this->skipIf(version_compare(phpversion(), '5.5.0', '<'));
        $this->Shell->runCommand(['options', 'Migrations.migrations', 'migrate']);
        $output = $this->out->output;
        $expected = "--ansi --help -h --no-ansi --no-interaction -n --no-lock --quiet -q --verbose -v --connection -c";
        $expected .= " --date -d --plugin -p --source -s --target -t\n";
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
        $this->skipIf(version_compare(phpversion(), '5.5.0', '<'));
        $this->Shell->runCommand(['options', 'Migrations.migrations', 'rollback']);
        $output = $this->out->output;
        $expected = "--ansi --help -h --no-ansi --no-interaction -n --no-lock --quiet -q --verbose -v --connection -c";
        $expected .= " --date -d --plugin -p --source -s --target -t\n";
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
        $this->skipIf(version_compare(phpversion(), '5.5.0', '<'));
        $this->Shell->runCommand(['options', 'Migrations.migrations', 'status']);
        $output = $this->out->output;
        $expected = "--ansi --help -h --no-ansi --no-interaction -n --quiet -q --verbose -v --connection -c";
        $expected .= " --format -f --plugin -p --source -s\n";
        $this->assertTextEquals($expected, $output);
    }
}
