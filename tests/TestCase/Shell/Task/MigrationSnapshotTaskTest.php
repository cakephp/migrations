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
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Inflector;
use Migrations\Test\TestCase\Shell\TestClassWithSnapshotTrait;

/**
 * MigrationSnapshotTaskTest class
 */
class MigrationSnapshotTaskTest extends TestCase
{
    use StringCompareTrait;

    public $fixtures = [
        'plugin.Migrations.Users',
        'plugin.Migrations.SpecialTags',
        'plugin.Migrations.SpecialPk',
        'plugin.Migrations.CompositePk',
        'plugin.Migrations.Products',
        'plugin.Migrations.Categories',
        'plugin.Migrations.Orders',
        'plugin.Migrations.Articles',
        'plugin.Migrations.Texts',
    ];

    /**
     * Mock of \Migrations\Shell\Task\MigrationSnapshotTask
     *
     * @var \Migrations\Shell\Task\MigrationSnapshotTask
     */
    public $Task;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS;
        $this->Task = $this->getTaskMock();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        unset($this->Task);
    }

    /**
     * Returns a MigrationSnapshotTask mock object properly configured
     *
     * @param array $mockedMethods List of methods to mock
     * @return \Migrations\Shell\Task\MigrationSnapshotTask mock
     */
    public function getTaskMock($mockedMethods = [])
    {
        $mockedMethods = $mockedMethods ?: [
            'dispatchShell',
            'findTables',
            'fetchTableName',
            'refreshDump',
        ];

        $inputOutput = $this->getMockBuilder('\Cake\Console\ConsoleIo')
            ->disableOriginalConstructor()
            ->getMock();

        $task = $this->getMockBuilder('\Migrations\Shell\Task\MigrationSnapshotTask')
            ->setMethods($mockedMethods)
            ->setConstructorArgs([$inputOutput])
            ->getMock();

        $task->name = 'Migration';
        $task->connection = 'test';
        $task->BakeTemplate = new BakeTemplateTask($inputOutput);
        $task->BakeTemplate->initialize();
        $task->BakeTemplate->interactive = false;

        return $task;
    }

    /**
     * Test that the MigrationSnapshotTask::getTableNames properly returns the table list
     * when we want tables from a plugin
     *
     * @return void
     */
    public function testGetTableNames()
    {
        $class = $this->getMockBuilder('\Migrations\Test\TestCase\Shell\TestClassWithSnapshotTrait')
            ->setMethods(['findTables', 'fetchTableName'])
            ->getMock();

        $class->expects($this->any())
            ->method('findTables')
            ->with('TestBlog')
            ->will($this->returnValue(['ArticlesTable.php', 'TagsTable.php']));

        $class->method('fetchTableName')
            ->will($this->onConsecutiveCalls(['articles_tags', 'articles'], ['articles_tags', 'tags']));

        $results = $class->getTableNames('TestBlog');
        $expected = ['articles_tags', 'articles', 'tags'];
        $this->assertEquals(array_values($expected), array_values($results));
    }

    /**
     * Test baking a snapshot
     *
     * @return void
     */
    public function testNotEmptySnapshot()
    {
        $this->Task->params['require-table'] = false;
        $this->Task->params['connection'] = 'test';
        $this->Task->params['plugin'] = 'BogusPlugin';

        $this->Task->expects($this->once())
            ->method('dispatchShell')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('migrations mark_migrated -t'),
                    $this->stringContains('-o -c test -p BogusPlugin')
                )
            );

        $this->Task->expects($this->once())
            ->method('refreshDump');

        $bakeName = $this->getBakeName('TestNotEmptySnapshot');
        $result = $this->Task->bake($bakeName);

        $this->assertNotEmpty(glob($this->Task->getPath() . '*_TestNotEmptySnapshot*.php'));
        $this->assertCorrectSnapshot($bakeName, $result);
    }

    /**
     * Test baking a snapshot
     *
     * @return void
     */
    public function testNotEmptySnapshotNoLock()
    {
        $this->Task->params['require-table'] = false;
        $this->Task->params['connection'] = 'test';
        $this->Task->params['plugin'] = 'BogusPlugin';
        $this->Task->params['no-lock'] = true;

        $this->Task->expects($this->once())
            ->method('dispatchShell')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('migrations mark_migrated -t'),
                    $this->stringContains('-o -c test -p BogusPlugin')
                )
            );

        $this->Task->expects($this->never())
            ->method('refreshDump');

        $bakeName = $this->getBakeName('TestNotEmptySnapshotNoLock');
        $result = $this->Task->bake($bakeName);
    }

    /**
     * Test baking a snapshot with the phinx auto-id feature disabled
     *
     * @return void
     */
    public function testAutoIdDisabledSnapshot()
    {
        $this->Task->params['require-table'] = false;
        $this->Task->params['disable-autoid'] = true;
        $this->Task->params['connection'] = 'test';
        $this->Task->params['plugin'] = 'BogusPlugin';

        $bakeName = $this->getBakeName('TestAutoIdDisabledSnapshot');
        $result = $this->Task->bake($bakeName);

        $this->assertNotEmpty(glob($this->Task->getPath() . '*_TestAutoIdDisabledSnapshot*.php'));
        $this->assertCorrectSnapshot($bakeName, $result);
    }

    /**
     * Test baking a snapshot for a plugin
     *
     * @return void
     */
    public function testPluginBlog()
    {
        $db = ConnectionManager::get('test');
        $table = new TableSchema('parts', [
            'id' => ['type' => 'integer', 'unsigned' => true],
            'name' => ['type' => 'string', 'length' => 255],
            'number' => ['type' => 'integer', 'null' => true, 'length' => 10, 'unsigned' => true],
        ]);
        $table->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
        $sql = $table->createSql($db);
        foreach ($sql as $stmt) {
            $db->execute($stmt);
        }

        $task = $this->getTaskMock(['in', 'err', 'dispatchShell', '_stop']);
        $task->params['require-table'] = false;
        $task->params['connection'] = 'test';
        $task->params['plugin'] = 'TestBlog';
        $task->plugin = 'TestBlog';

        $bakeName = $this->getBakeName('TestPluginBlog');
        $result = $task->bake($bakeName);

        $this->assertCorrectSnapshot($bakeName, $result);

        $sql = $table->dropSql($db);
        foreach ($sql as $stmt) {
            $db->execute($stmt);
        }
    }

    /**
     * Test that using MigrationSnapshotTask::fetchTableName in a Table object class
     * where the table name is composed with the database name (e.g. mydb.mytable)
     * will return :
     * - only the table name if the current connection `database` parameter is the first part
     * of the table name
     * - the full string (e.g. mydb.mytable) if the current connection `database` parameter
     * is not the first part of the table name
     *
     * @return void
     */
    public function testFetchTableNames()
    {
        $class = new TestClassWithSnapshotTrait();
        $expected = ['alternative.special_tags'];
        $this->assertEquals($expected, $class->fetchTableName('SpecialTagsTable.php', 'TestBlog'));

        ConnectionManager::setConfig('alternative', [
            'database' => 'alternative',
        ]);
        $class->connection = 'alternative';
        $expected = ['special_tags'];
        $this->assertEquals($expected, $class->fetchTableName('SpecialTagsTable.php', 'TestBlog'));

        ConnectionManager::drop('alternative');
        ConnectionManager::setConfig('alternative', [
            'schema' => 'alternative',
        ]);
        $class->connection = 'alternative';
        $expected = ['special_tags'];
        $this->assertEquals($expected, $class->fetchTableName('SpecialTagsTable.php', 'TestBlog'));
    }

    /**
     * Get the baked filename based on the current db environment
     *
     * @param string $name Name of the baked file, unaware of the DB environment
     * @return string Baked filename
     */
    public function getBakeName($name)
    {
        $dbenv = getenv("DB");
        if ($dbenv !== 'mysql') {
            $name .= ucfirst($dbenv);
        } else {
            $dbv = getenv('DBV');
            if (!empty($dbv)) {
                $name .= $dbv;
            }
        }

        return $name;
    }

    /**
     * Assert that the $result matches the content of the baked file
     *
     * @param string $bakeName Name of the file to compare to the test
     * @param string $result Results generated by the test to be compared
     * @return void
     */
    public function assertCorrectSnapshot($bakeName, $result)
    {
        $dbenv = getenv("DB");
        $bakeName = Inflector::underscore($bakeName);
        if (file_exists($this->_compareBasePath . $dbenv . DS . $bakeName . '.php')) {
            $this->assertSameAsFile($dbenv . DS . $bakeName . '.php', $result);
        } else {
            $this->assertSameAsFile($bakeName . '.php', $result);
        }
    }
}
