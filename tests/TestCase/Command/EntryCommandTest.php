<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         4.3.0
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * EntryCommand Test
 */
class EntryCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();

        Configure::write('Migrations.backend', 'builtin');
    }

    /**
     * Test execute() generating help
     *
     * @return void
     */
    public function testExecuteHelp()
    {
        $this->exec('migrations --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Available Commands');
        $this->assertOutputContains('migrations migrate');
        $this->assertOutputContains('migrations status');
        $this->assertOutputContains('migrations rollback');
    }

    /**
     * Test execute() generating help
     *
     * @return void
     */
    public function testExecuteMissingCommand()
    {
        $this->exec('migrations derp');

        $this->assertExitError();
        $this->assertErrorContains('Could not find migrations command named `derp`');
    }
}
