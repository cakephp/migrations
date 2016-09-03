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
class SeedTaskTest extends TestCase
{
    use StringCompareTrait;

    public $fixtures = ['core.articles', 'plugin.migrations.events'];

    /**
     * ConsoleIo mock
     *
     * @var \Cake\Console\ConsoleIo|\PHPUnit_Framework_MockObject_MockObject
     */
    public $io;

    /**
     * Test subject
     *
     * @var \Migrations\Shell\Task\SeedTask
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
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Seeds' . DS;
        $inputOutput = $this->getMockBuilder('Cake\Console\ConsoleIo')
            ->disableOriginalConstructor()
            ->getMock();

        $this->Task = $this->getMockBuilder('\Migrations\Shell\Task\SeedTask')
            ->setMethods(['in', 'err', 'createFile', '_stop', 'error'])
            ->setConstructorArgs([$inputOutput])
            ->getMock();

        $this->Task->name = 'Seeds';
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
    public function testBasicBaking()
    {
        $this->Task->args = [
            'articles'
        ];
        $result = $this->Task->bake('Articles');
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }


    /**
     * Test with data, all fields, no limit
     *
     * @return void
     */
    public function testWithData()
    {
        $this->Task->args = ['articles'];
        $this->Task->params['data'] = true;

        $path = __FUNCTION__ . '.php';
        if (getenv('DB') == 'pgsql') {
            $path = getenv('DB') . DS . $path;
        }

        $result = $this->Task->bake('Articles');
        $this->assertSameAsFile($path, $result);
    }

    /**
     * Test with data and fields specified
     *
     * @return void
     */
    public function testWithDataAndFields()
    {
        $this->Task->args = ['articles'];
        $this->Task->params['data'] = true;
        $this->Task->params['fields'] = 'title,body';

        $result = $this->Task->bake('Articles');
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }

    /**
     * Test with data, fields and limit specified
     *
     * @return void
     */
    public function testWithDataAndFieldsAndLimit()
    {
        $this->Task->args = ['articles'];
        $this->Task->params['data'] = true;
        $this->Task->params['limit'] = 2;

        $path = __FUNCTION__ . '.php';
        if (getenv('DB') == 'pgsql') {
            $path = getenv('DB') . DS . $path;
        }

        $result = $this->Task->bake('Articles');
        $this->assertSameAsFile($path, $result);
    }

    /**
     * Test with table specified
     *
     * @return void
     */
    public function testWithQuotesInData()
    {
        $this->Task->args = ['articles'];
        $this->Task->params['data'] = true;
        $this->Task->params['table'] = 'events';
        $this->Task->params['fields'] = 'title,description';

        $result = $this->Task->bake('Articles');

        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }
}
