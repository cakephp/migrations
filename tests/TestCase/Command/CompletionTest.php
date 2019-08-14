<?php
declare(strict_types=1);

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
namespace Migrations\Test\TestCase\Command;

use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Class CommandTaskTest
 */
class CompletionTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->useCommandRunner();
    }

    /**
     * tearDown
     *
     * @return void
     */
    public function tearDown(): void
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
        $this->exec('completion subcommands migrations.migrations');
        $expected = [
            'main create dump mark_migrated migrate rollback status',
        ];
        $actual = $this->_out->messages();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsOptionsCreate()
    {
        $this->exec('completion options migrations.migrations create');
        $this->assertCount(1, $this->_out->messages());
        $output = $this->_out->messages()[0];
        $expected = "--ansi --help -h --no-ansi --no-interaction -n --quiet -q --verbose -v --class -l --connection";
        $expected .= " -c --plugin -p --source -s --template -t";
        $outputExplode = explode(' ', trim($output));
        sort($outputExplode);
        $expectedExplode = explode(' ', $expected);
        sort($expectedExplode);

        $this->assertEquals($outputExplode, $expectedExplode);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsOptionsMarkMigrated()
    {
        $this->exec('completion options migrations.migrations mark_migrated');
        $this->assertCount(1, $this->_out->messages());
        $output = $this->_out->messages()[0];
        $expected = "--ansi --help -h --no-ansi --no-interaction -n --quiet -q --verbose -v --connection -c";
        $expected .= " --exclude -x --only -o --plugin -p --source -s";
        $outputExplode = explode(' ', trim($output));
        sort($outputExplode);
        $expectedExplode = explode(' ', $expected);
        sort($expectedExplode);

        $this->assertEquals($outputExplode, $expectedExplode);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsOptionsMigrate()
    {
        $this->exec('completion options migrations.migrations migrate');
        $this->assertCount(1, $this->_out->messages());
        $output = $this->_out->messages()[0];
        $expected = "--ansi --dry-run -x --fake --help -h --no-ansi --no-interaction";
        $expected .= " -n --no-lock --quiet -q --verbose -v --connection -c --date -d";
        $expected .= " --plugin -p --source -s --target -t";
        $outputExplode = explode(' ', trim($output));
        sort($outputExplode);
        $expectedExplode = explode(' ', $expected);
        sort($expectedExplode);

        $this->assertEquals($outputExplode, $expectedExplode);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsOptionsRollback()
    {
        $this->exec('completion options migrations.migrations rollback');
        $this->assertCount(1, $this->_out->messages());
        $output = $this->_out->messages()[0];
        $expected = "--ansi --dry-run -x --fake --help -h --no-ansi --no-interaction -n --no-lock";
        $expected .= " --quiet -q --verbose -v --connection -c --date -d --plugin -p --source -s --target -t";
        $outputExplode = explode(' ', trim($output));
        sort($outputExplode);
        $expectedExplode = explode(' ', $expected);
        sort($expectedExplode);

        $this->assertEquals($outputExplode, $expectedExplode);
    }

    /**
     * Test that subcommands from the Migrations shell are correctly returned
     * if needed with the autocompletion feature
     *
     * @return void
     */
    public function testMigrationsOptionsStatus()
    {
        $this->exec('completion options migrations.migrations status');
        $this->assertCount(1, $this->_out->messages());
        $output = $this->_out->messages()[0];
        $expected = "--ansi --help -h --no-ansi --no-interaction -n --quiet -q --verbose -v --connection -c";
        $expected .= " --format -f --plugin -p --source -s";
        $outputExplode = explode(' ', trim($output));
        sort($outputExplode);
        $expectedExplode = explode(' ', $expected);
        sort($expectedExplode);

        $this->assertEquals($outputExplode, $expectedExplode);
    }
}
