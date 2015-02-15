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

use Bake\Shell\Task\TemplateTask;
use Migrations\Shell\Task\MigrationSnapshotTask;
use Cake\Core\Plugin;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;

/**
 * MigrationSnapshotTaskTest class
 */
class MigrationSnapshotTaskTest extends TestCase
{
    use StringCompareTrait;

    public $fixtures = [
        'plugin.migrations.users',
        'plugin.migrations.special_tags',
    ];

    /**
     * setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS;
        $inputOutput = $this->getMock('Cake\Console\ConsoleIo', [], [], '', false);

        $this->Task = $this->getMock(
            'Migrations\Shell\Task\MigrationSnapshotTask',
            ['in', 'err', 'createFile', '_stop'],
            [$inputOutput]
        );
        $this->Task->name = 'Migration';
        $this->Task->connection = 'test';
        $this->Task->Template = new TemplateTask($inputOutput);
        $this->Task->Template->initialize();
        $this->Task->Template->interactive = false;
    }

    public function testNotEmptySnapshot()
    {
        $this->Task->params['require-table'] = false;
        $result = $this->Task->bake('NotEmptySnapshot');
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }
}
