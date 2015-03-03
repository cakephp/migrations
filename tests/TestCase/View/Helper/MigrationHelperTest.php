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
        'plugin.migrations.users',
        'plugin.migrations.special_tags',
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
        $this->loadFixtures('SpecialTags');

        $this->values = [
            'null' => 'NULL',
            'integerNull' => null,
            'integerLimit' => null,
            'comment' => null,
        ];

        if (getenv('DB') == 'mysql') {
            $this->values = [
                'null' => null,
                'integerNull' => null,
                'integerLimit' => 11,
                'comment' => '',
            ];
        }

        if (getenv('DB') == 'pgsql') {
            $this->values = [
                'null' => null,
                'integerNull' => null,
                'integerLimit' => 10,
                'comment' => null,
            ];
        }
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
     * @covers Migrations\View\Helper\MigrationHelper::columns
     */
    public function testColumns()
    {
        $this->assertEquals([
            'username' => [
                'columnType' => 'string',
                'options' => [
                    'limit' => 256,
                    'null' => true,
                    'default' => $this->values['null'],
                    'precision' => null,
                    'comment' => $this->values['comment'],
                ],
            ],
            'password' => [
                'columnType' => 'string',
                'options' => [
                    'limit' => 256,
                    'null' => true,
                    'default' => $this->values['null'],
                    'precision' => null,
                    'comment' => $this->values['comment'],
                ],
            ],
            'created' => [
                'columnType' => 'timestamp',
                'options' => [
                    'limit' => null,
                    'null' => true,
                    'default' => $this->values['null'],
                    'precision' => null,
                    'comment' => $this->values['comment'],
                ],
            ],
            'updated' => [
                'columnType' => 'timestamp',
                'options' => [
                    'limit' => null,
                    'null' => true,
                    'default' => $this->values['null'],
                    'precision' => null,
                    'comment' => $this->values['comment'],
                ],
            ],
        ], $this->Helper->columns('users'));
    }

    /**
     * @covers Migrations\View\Helper\MigrationHelper::column
     */
    public function testColumn()
    {
        $tableSchema = $this->Collection->describe('users');
        $this->assertEquals([
            'columnType' => 'integer',
            'options' => [
                'limit' => $this->values['integerLimit'],
                'null' => false,
                'default' => $this->values['integerNull'],
                'precision' => null,
                'comment' => $this->values['comment'],
                'signed' => true,
            ],
        ], $this->Helper->column($tableSchema, 'id'));

        $this->assertEquals([
            'columnType' => 'string',
            'options' => [
                'limit' => 256,
                'null' => true,
                'default' => $this->values['null'],
                'precision' => null,
                'comment' => $this->values['comment'],
            ],
        ], $this->Helper->column($tableSchema, 'username'));


        $this->assertEquals([
            'columnType' => 'string',
            'options' => [
                'limit' => 256,
                'null' => true,
                'default' => $this->values['null'],
                'precision' => null,
                'comment' => $this->values['comment'],
            ],
        ], $this->Helper->column($tableSchema, 'password'));


        $this->assertEquals([
            'columnType' => 'timestamp',
            'options' => [
                'limit' => null,
                'null' => true,
                'default' => $this->values['null'],
                'precision' => null,
                'comment' => $this->values['comment'],
            ],
        ], $this->Helper->column($tableSchema, 'created'));

        $this->assertEquals([
            'columnType' => 'timestamp',
            'options' => [
                'limit' => null,
                'null' => true,
                'default' => $this->values['null'],
                'precision' => null,
                'comment' => $this->values['comment'],
            ],
        ], $this->Helper->column($tableSchema, 'updated'));
    }

    /**
     * @covers Migrations\View\Helper\MigrationHelper::value
     */
    public function testValue()
    {
        $this->assertEquals('null', $this->Helper->value(null));
        $this->assertEquals('null', $this->Helper->value('null'));
        $this->assertEquals('true', $this->Helper->value(true));
        $this->assertEquals('false', $this->Helper->value(false));
        $this->assertEquals(1, $this->Helper->value(1));
        $this->assertEquals(-1, $this->Helper->value(-1));
        $this->assertEquals(1, $this->Helper->value('1'));
        $this->assertInternalType('int', $this->Helper->value('1'));
        $this->assertEquals("'one'", $this->Helper->value('one'));
        $this->assertEquals("'o\\\"ne'", $this->Helper->value('o"ne'));
    }

    /**
     * @covers Migrations\View\Helper\MigrationHelper::attributes
     */
    public function testAttributes()
    {
        $this->assertEquals([
            'limit' => $this->values['integerLimit'],
            'null' => false,
            'default' => $this->values['integerNull'],
            'precision' => null,
            'comment' => $this->values['comment'],
            'signed' => true,
        ], $this->Helper->attributes('users', 'id'));

        $this->assertEquals([
            'limit' => 256,
            'null' => true,
            'default' => $this->values['null'],
            'precision' => null,
            'comment' => $this->values['comment'],
        ], $this->Helper->attributes('users', 'username'));


        $this->assertEquals([
            'limit' => 256,
            'null' => true,
            'default' => $this->values['null'],
            'precision' => null,
            'comment' => $this->values['comment'],
        ], $this->Helper->attributes('users', 'password'));


        $this->assertEquals([
            'limit' => null,
            'null' => true,
            'default' => $this->values['null'],
            'precision' => null,
            'comment' => $this->values['comment'],
        ], $this->Helper->attributes('users', 'created'));

        $this->assertEquals([
            'limit' => null,
            'null' => true,
            'default' => $this->values['null'],
            'precision' => null,
            'comment' => null,
        ], $this->Helper->attributes('users', 'updated'));

        $this->assertEquals([
            'limit' => 11,
            'null' => false,
            'default' => $this->values['integerNull'],
            'precision' => null,
            'comment' => $this->values['comment'],
            'signed' => true,
        ], $this->Helper->attributes('special_tags', 'article_id'));
    }

    /**
     * @covers Migrations\View\Helper\MigrationHelper::stringifyList
     */
    public function testStringifyList()
    {
        $this->assertEquals("", $this->Helper->stringifyList([]));
        $this->assertEquals("
        'key' => 'value',
    ", $this->Helper->stringifyList([
            'key' => 'value',
        ]));
        $this->assertEquals("
        'key' => 'value',
        'other_key' => 'other_value',
    ", $this->Helper->stringifyList([
            'key' => 'value',
            'other_key' => 'other_value',
        ]));
        $this->assertEquals("
        'key' => 'value',
        'other_key' => [
            'key' => 'value',
            'other_key' => 'other_value',
        ],
    ", $this->Helper->stringifyList([
            'key' => 'value',
            'other_key' => [
                'key' => 'value',
                'other_key' => 'other_value',
            ],
        ]));
    }
}
