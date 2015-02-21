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
use Migrations\Shell\Task\MigrationTask;

/**
 * MigrationTaskTest class
 */
class MigrationTaskTest extends TestCase
{
    use StringCompareTrait;

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
            'Migrations\Shell\Task\MigrationTask',
            ['in', 'err', 'createFile', '_stop'],
            [$inputOutput]
        );
        $this->Task->name = 'Migration';
        $this->Task->connection = 'test';
        $this->Task->BakeTemplate = new BakeTemplateTask($inputOutput);
        $this->Task->BakeTemplate->initialize();
        $this->Task->BakeTemplate->interactive = false;
    }

    /**
     * Test empty migration.
     *
     * @return void
     */
    public function testNoContents()
    {
        $result = $this->Task->bake('NoContents');
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }

    /**
     * Test the excute method.
     *
     * @return void
     */
    public function testCreate()
    {
        $this->Task->args = [
            'create_users',
            'name'
        ];
        $result = $this->Task->bake('CreateUsers');
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);

        $this->Task->args = [
            'create_users',
            'name',
            'created',
            'modified'
        ];
        $result = $this->Task->bake('CreateUsers');
        $this->assertSameAsFile(__FUNCTION__ . 'Datetime.php', $result);
    }

    /**
     * @covers Migrations\Shell\Task\MigrationTask::detectAction
     */
    public function testDetectAction()
    {
        $this->assertEquals(
            ['create_table', 'groups'],
            $this->Task->detectAction('CreateGroups')
        );
        $this->assertEquals(
            ['create_table', 'users'],
            $this->Task->detectAction('CreateUsers')
        );
        $this->assertEquals(
            ['create_table', 'groups_users'],
            $this->Task->detectAction('CreateGroupsUsers')
        );

        $this->assertEquals(
            ['drop_table', 'groups'],
            $this->Task->detectAction('DropGroups')
        );
        $this->assertEquals(
            ['drop_table', 'users'],
            $this->Task->detectAction('DropUsers')
        );
        $this->assertEquals(
            ['drop_table', 'groups_users'],
            $this->Task->detectAction('DropGroupsUsers')
        );

        $this->assertEquals(
            ['add_field', 'groups'],
            $this->Task->detectAction('AddFieldToGroups')
        );
        $this->assertEquals(
            ['add_field', 'users'],
            $this->Task->detectAction('AddFieldToUsers')
        );
        $this->assertEquals(
            ['add_field', 'groups_users'],
            $this->Task->detectAction('AddFieldToGroupsUsers')
        );
        $this->assertEquals(
            ['add_field', 'groups'],
            $this->Task->detectAction('AddThingToGroups')
        );
        $this->assertEquals(
            ['add_field', 'users'],
            $this->Task->detectAction('AddAnotherFieldToUsers')
        );
        $this->assertEquals(
            ['add_field', 'groups_users'],
            $this->Task->detectAction('AddSomeFieldToGroupsUsers')
        );

        $this->assertEquals(
            ['drop_field', 'groups'],
            $this->Task->detectAction('RemoveFieldsFromGroups')
        );
        $this->assertEquals(
            ['drop_field', 'users'],
            $this->Task->detectAction('RemoveFieldsFromUsers')
        );
        $this->assertEquals(
            ['drop_field', 'groups_users'],
            $this->Task->detectAction('RemoveFieldsFromGroupsUsers')
        );
        $this->assertEquals(
            ['drop_field', 'groups'],
            $this->Task->detectAction('RemoveThingFromGroups')
        );
        $this->assertEquals(
            ['drop_field', 'users'],
            $this->Task->detectAction('RemoveAnotherFieldFromUsers')
        );
        $this->assertEquals(
            ['drop_field', 'groups_users'],
            $this->Task->detectAction('RemoveSomeFieldFromGroupsUsers')
        );

        $this->assertEquals(
            ['alter_table', 'groups'],
            $this->Task->detectAction('AlterGroups')
        );
        $this->assertEquals(
            ['alter_table', 'users'],
            $this->Task->detectAction('AlterUsers')
        );
        $this->assertEquals(
            ['alter_table', 'groups_users'],
            $this->Task->detectAction('AlterGroupsUsers')
        );

        $this->assertEquals(
            null,
            $this->Task->detectAction('ReaddColumnsToTable')
        );
    }
}
