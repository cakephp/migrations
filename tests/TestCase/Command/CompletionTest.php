<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Class CommandTaskTest
 */
class CompletionTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

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
            'orm-cache-build orm-cache-clear create dump mark_migrated migrate rollback seed status',
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
        $expected = '--class -l --connection -c --help -h --path --plugin -p --quiet';
        $expected .= ' -q --source -s --template -t --verbose -v';
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
        $expected = '--connection -c --exclude -x --help -h --only -o --plugin -p --quiet -q';
        $expected .= ' --source -s --target -t --verbose -v';
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
        $expected = '--connection -c --date -d --dry-run -x --fake --help -h --no-lock --plugin -p';
        $expected .= ' --quiet -q --source -s --target -t --verbose -v';
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
        $expected = '--connection -c --date -d --dry-run -x --fake --force -f --help -h --no-lock --plugin -p';
        $expected .= ' --quiet -q --source -s --target -t --verbose -v';
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
        $expected = '--connection -c --format -f --help -h --plugin -p --quiet -q --source -s --verbose -v';
        $outputExplode = explode(' ', trim($output));
        sort($outputExplode);
        $expectedExplode = explode(' ', $expected);
        sort($expectedExplode);

        $this->assertEquals($outputExplode, $expectedExplode);
    }
}
