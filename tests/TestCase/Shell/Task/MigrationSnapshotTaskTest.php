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
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Inflector;

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
        'plugin.migrations.products',
        'plugin.migrations.categories',
        'plugin.migrations.orders',
        'plugin.migrations.articles'
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
        $this->Task = $this->getTaskMock();
    }

    public function getTaskMock($mockedMethods = [])
    {
        $mockedMethods = $mockedMethods ?: ['in', 'err', 'dispatchShell', '_stop', 'findTables', 'fetchTableName'];
        $inputOutput = $this->getMock('Cake\Console\ConsoleIo', [], [], '', false);

        $task = $this->getMock(
            'Migrations\Shell\Task\MigrationSnapshotTask',
            $mockedMethods,
            [$inputOutput]
        );
        $task->name = 'Migration';
        $task->connection = 'test';
        $task->BakeTemplate = new BakeTemplateTask($inputOutput);
        $task->BakeTemplate->initialize();
        $task->BakeTemplate->interactive = false;
        return $task;
    }

    public function testGetTableNames()
    {
        $this->Task->expects($this->any())
            ->method('findTables')
            ->with('Blog')
            ->will($this->returnValue(['ArticlesTable.php', 'TagsTable.php']));

        $this->Task->method('fetchTableName')
            ->will($this->onConsecutiveCalls(['articles_tags', 'articles'], ['articles_tags', 'tags']));

        $results = $this->Task->getTableNames('Blog');
        $expected = ['articles_tags', 'articles', 'tags'];
        $this->assertEquals(array_values($expected), array_values($results));
    }

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

        $bakeName = $this->getBakeName('TestNotEmptySnapshot');
        $result = $this->Task->bake($bakeName);

        $this->assertCorrectSnapshot($bakeName, $result);
    }

    public function testAutoIdDisabledSnapshot()
    {
        $this->Task->params['require-table'] = false;
        $this->Task->params['disable-autoid'] = true;
        $this->Task->params['connection'] = 'test';
        $this->Task->params['plugin'] = 'BogusPlugin';

        $bakeName = $this->getBakeName('TestAutoIdDisabledSnapshot');
        $result = $this->Task->bake($bakeName);

        $this->assertCorrectSnapshot($bakeName, $result);
    }

    public function testPluginBlog()
    {
        $task = $this->getTaskMock(['in', 'err', 'dispatchShell', '_stop']);
        $task->params['require-table'] = false;
        $task->params['connection'] = 'test';
        $task->params['plugin'] = 'Blog';
        $task->plugin = 'Blog';

        $bakeName = $this->getBakeName('TestPluginBlog');
        $result = $task->bake($bakeName);

        $this->assertCorrectSnapshot($bakeName, $result);
    }

    public function getBakeName($name)
    {
        $dbenv = getenv("DB");
        if ($dbenv !== 'mysql') {
            $name .= ucfirst($dbenv);
        }

        return $name;
    }

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
