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
        $inputOutput = $this->getMockBuilder('\Cake\Console\ConsoleIo')
            ->disableOriginalConstructor()
            ->getMock();

        $this->Task = $this->getMockBuilder('\Migrations\Shell\Task\MigrationTask')
            ->setMethods(['in', 'err', 'createFile', '_stop', 'abort', 'error'])
            ->setConstructorArgs([$inputOutput])
            ->getMock();

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
     * Test the execute method.
     *
     * @return void
     */
    public function testCreate()
    {
        $this->Task->args = [
            'create_users',
            'name',
        ];
        $result = $this->Task->bake('CreateUsers');
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);

        $this->Task->args = [
            'create_users',
            'name',
            'created',
            'modified',
        ];
        $result = $this->Task->bake('CreateUsers');
        $this->assertSameAsFile(__FUNCTION__ . 'Datetime.php', $result);

        $this->Task->args = [
            'create_users',
            'id:integer:primary_key',
            'name',
            'created',
            'modified',
        ];
        $result = $this->Task->bake('CreateUsers');
        $this->assertSameAsFile(__FUNCTION__ . 'PrimaryKey.php', $result);

        $this->Task->args = [
            'create_users',
            'id:uuid:primary_key',
            'name',
            'created',
            'modified',
        ];
        $result = $this->Task->bake('CreateUsers');
        $this->assertSameAsFile(__FUNCTION__ . 'PrimaryKeyUuid.php', $result);

        $this->Task->args = [
            'create_users',
            'name:string[128]',
            'counter:integer[8]',
        ];
        $result = $this->Task->bake('CreateUsers');
        $this->assertSameAsFile(__FUNCTION__ . 'FieldLength.php', $result);
    }

    /**
     * Tests that baking a migration with the name as another will throw an exception.
     *
     * @expectedException \Cake\Console\Exception\StopException
     * @expectedExceptionMessage A migration with the name `CreateUsers` already exists. Please use a different name.
     */
    public function testCreateDuplicateName()
    {
        $inputOutput = $this->getMockBuilder('\Cake\Console\ConsoleIo')
            ->disableOriginalConstructor()
            ->getMock();

        $task = $this->getMockBuilder('\Migrations\Shell\Task\MigrationTask')
            ->setMethods(['in', 'err', '_stop', 'error'])
            ->setConstructorArgs([$inputOutput])
            ->getMock();

        $task->name = 'Migration';
        $task->connection = 'test';
        $task->BakeTemplate = new BakeTemplateTask($inputOutput);
        $task->BakeTemplate->initialize();
        $task->BakeTemplate->interactive = false;

        $task->bake('CreateUsers');
        $task->bake('CreateUsers');
    }

    /**
     * Tests that baking a migration with the name as another with the parameter "force", will delete the existing file.
     */
    public function testCreateDuplicateNameWithForce()
    {
        $inputOutput = $this->getMockBuilder('\Cake\Console\ConsoleIo')
            ->disableOriginalConstructor()
            ->getMock();

        $task = $this->getMockBuilder('\Migrations\Shell\Task\MigrationTask')
            ->setMethods(['in', 'err', '_stop', 'abort'])
            ->setConstructorArgs([$inputOutput])
            ->getMock();

        $task->name = 'Migration';
        $task->connection = 'test';
        $task->params['force'] = true;
        $task->BakeTemplate = new BakeTemplateTask($inputOutput);
        $task->BakeTemplate->initialize();
        $task->BakeTemplate->interactive = false;

        $task->bake('CreateUsers');

        $file = glob(ROOT . 'config' . DS . 'Migrations' . DS . '*_CreateUsers.php');
        $filePath = current($file);
        sleep(1);

        $task->bake('CreateUsers');
        $file = glob(ROOT . 'config' . DS . 'Migrations' . DS . '*_CreateUsers.php');
        $this->assertNotEquals($filePath, current($file));
    }

    /**
     * Test that adding a field or altering a table with a primary
     * key will error out
     *
     * @return void
     */
    public function testAddPrimaryKeyToExistingTable()
    {
        $this->Task->expects($this->any())
            ->method('abort');

        $this->Task->args = [
            'add_pk_to_users',
            'somefield:primary_key',
        ];
        $this->Task->bake('AddPkToUsers');

        $this->Task->args = [
            'alter_users',
            'somefield:primary_key',
        ];
        $this->Task->bake('AlterUsers');
    }

    /**
     * @covers Migrations\Shell\Task\MigrationTask::detectAction
     * @return void
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
            ['create_table', 'articles_i18n'],
            $this->Task->detectAction('CreateArticlesI18n')
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
            ['drop_table', 'articles_i18n'],
            $this->Task->detectAction('DropArticlesI18n')
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
            ['add_field', 'groups'],
            $this->Task->detectAction('AddTokenToGroups')
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
            ['add_field', 'todos'],
            $this->Task->detectAction('AddSomeFieldToTodos')
        );
        $this->assertEquals(
            ['add_field', 'articles_i18n'],
            $this->Task->detectAction('AddSomeFieldToArticlesI18n')
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
            ['drop_field', 'fromages'],
            $this->Task->detectAction('RemoveSomeFieldFromFromages')
        );
        $this->assertEquals(
            ['drop_field', 'fromages'],
            $this->Task->detectAction('RemoveFromageFromFromages')
        );
        $this->assertEquals(
            ['drop_field', 'articles_i18n'],
            $this->Task->detectAction('RemoveFromageFromArticlesI18n')
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
            ['alter_table', 'articles_i18n'],
            $this->Task->detectAction('AlterArticlesI18n')
        );

        $this->assertSame(
            [],
            $this->Task->detectAction('ReaddColumnsToTable')
        );

        $this->assertEquals(
            ['alter_field', 'groups'],
            $this->Task->detectAction('AlterFieldOnGroups')
        );
        $this->assertEquals(
            ['alter_field', 'users'],
            $this->Task->detectAction('AlterFieldOnUsers')
        );
        $this->assertEquals(
            ['alter_field', 'groups_users'],
            $this->Task->detectAction('AlterFieldOnGroupsUsers')
        );
        $this->assertEquals(
            ['alter_field', 'todos'],
            $this->Task->detectAction('AlterFieldOnTodos')
        );
        $this->assertEquals(
            ['alter_field', 'articles_i18n'],
            $this->Task->detectAction('AlterFieldOnArticlesI18n')
        );
    }
}
