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

use Bake\Shell\Task\BakeTemplateTask;
use Cake\Core\Plugin;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use Migrations\Shell\Task\MigrationSnapshotTask;
use Phinx\Migration\Util;

/**
 * MigrationSnapshotTaskTest class
 */
class MigrationSnapshotTaskTest extends TestCase
{
    use StringCompareTrait;

    public $fixtures = [
        'plugin.migrations.users',
        'plugin.migrations.special_tags',
        'plugin.migrations.special_pk',
        'plugin.migrations.composite_pk',
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
            ['in', 'err', 'dispatchShell', '_stop'],
            [$inputOutput]
        );
        $this->Task->name = 'Migration';
        $this->Task->connection = 'test';
        $this->Task->BakeTemplate = new BakeTemplateTask($inputOutput);
        $this->Task->BakeTemplate->initialize();
        $this->Task->BakeTemplate->interactive = false;
    }

    public function testNotEmptySnapshot()
    {
        $this->Task->params['require-table'] = false;

        $version = Util::getCurrentTimestamp();

        $this->Task->expects($this->once())
            ->method('dispatchShell')
            ->with('migrations', 'mark_migrated', $version);

        $result = $this->Task->bake('NotEmptySnapshot');
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }
}
