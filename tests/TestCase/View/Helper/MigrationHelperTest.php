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
namespace Migrations\Test;

use Cake\Cache\Cache;
use Cake\Core\Plugin;
use Cake\Database\Schema\Collection;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use Migrations\View\Helper\MigrationHelper;

/**
 * Tests the ConfigurationTrait
 */
class MigrationHelperTest extends TestCase
{
    public $fixtures = [
        'core.users'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->Connection = ConnectionManager::get('test');
        $this->Collection = new Collection($this->Connection);
        $this->View = new View();
        $this->Helper = new MigrationHelper($this->View, [
            'collection' => $this->Collection
        ]);
        Cache::clear(false, '_cake_model_');
        Cache::enable();
        $this->loadFixtures('Users');
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        unset($this->Helper, $this->View, $this->Collection, $this->Connection);
    }

    /**
     * @covers Migrations\View\Helper\MigrationHelper::tableMethod
     */
    public function testTableMethod()
    {
        $this->assertEquals('drop', $this->Helper->tableMethod('drop_table'));
        $this->assertEquals('create', $this->Helper->tableMethod('create_table'));
        $this->assertEquals('update', $this->Helper->tableMethod('other_method'));
    }

    /**
     * @covers Migrations\View\Helper\MigrationHelper::indexMethod
     */
    public function testIndexMethod()
    {
        $this->assertEquals('removeIndex', $this->Helper->indexMethod('drop_field'));
        $this->assertEquals('addIndex', $this->Helper->indexMethod('add_field'));
        $this->assertEquals('addIndex', $this->Helper->indexMethod('alter_field'));
    }

    /**
     * @covers Migrations\View\Helper\MigrationHelper::columnMethod
     */
    public function testColumnMethod()
    {
        $this->assertEquals('removeColumn', $this->Helper->columnMethod('drop_field'));
        $this->assertEquals('addColumn', $this->Helper->columnMethod('add_field'));
        $this->assertEquals('addColumn', $this->Helper->columnMethod('alter_field'));
    }

    /**
     * @covers Migrations\View\Helper\MigrationHelper::columnMethod
     */
    public function testColumns()
    {
        $this->assertEquals('removeColumn', $this->Helper->columns('users'));
    }
}
