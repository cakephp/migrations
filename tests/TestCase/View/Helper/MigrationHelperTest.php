<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase\View\Helper;

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
    /**
     * @var string[]
     */
    protected $fixtures = [
        'plugin.Migrations.Users',
        'plugin.Migrations.SpecialTags',
    ];

    /**
     * @var \Cake\Datasource\ConnectionInterface
     */
    protected $connection;

    /**
     * @var \Cake\Database\Schema\Collection
     */
    protected $collection;

    /**
     * @var \Cake\View\View
     */
    protected $view;

    /**
     * @var \Migrations\View\Helper\MigrationHelper
     */
    protected $helper;

    /**
     * @var array
     */
    protected $types;

    /**
     * @var array
     */
    protected $values;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->connection = ConnectionManager::get('test');
        $this->collection = new Collection($this->connection);
        $this->view = new View();
        $this->helper = new MigrationHelper($this->view, [
            'collection' => $this->collection,
        ]);

        $this->types = [
            'timestamp' => 'timestamp',
        ];
        $this->values = [
            'null' => null,
            'integerLimit' => null,
            'integerNull' => null,
            'precision' => null,
            'comment' => null,
        ];

        if (getenv('DB') === 'mysql') {
            $this->values = [
                'null' => null,
                'integerLimit' => null,
                'integerNull' => null,
                'precision' => null,
                'comment' => '',
            ];
        }

        if (getenv('DB') === 'pgsql') {
            $this->values = [
                'null' => null,
                'integerLimit' => null,
                'integerNull' => null,
                'comment' => null,
                'precision' => 6,
            ];
        }
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->helper, $this->view, $this->collection, $this->connection);
    }

    /**
     * @covers \Migrations\View\Helper\MigrationHelper::tableMethod()
     */
    public function testTableMethod()
    {
        $this->assertSame('drop', $this->helper->tableMethod('drop_table'));
        $this->assertSame('create', $this->helper->tableMethod('create_table'));
        $this->assertSame('update', $this->helper->tableMethod('other_method'));
    }

    /**
     * @covers \Migrations\View\Helper\MigrationHelper::indexMethod()
     */
    public function testIndexMethod()
    {
        $this->assertSame('removeIndex', $this->helper->indexMethod('drop_field'));
        $this->assertSame('addIndex', $this->helper->indexMethod('add_field'));
        $this->assertSame('addIndex', $this->helper->indexMethod('alter_field'));
    }

    /**
     * @covers \Migrations\View\Helper\MigrationHelper::columnMethod()
     */
    public function testColumnMethod()
    {
        $this->assertSame('removeColumn', $this->helper->columnMethod('drop_field'));
        $this->assertSame('addColumn', $this->helper->columnMethod('add_field'));
        $this->assertSame('changeColumn', $this->helper->columnMethod('alter_field'));
    }

    /**
     * @covers \Migrations\View\Helper\MigrationHelper::columns()
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
                'columnType' => $this->types['timestamp'],
                'options' => [
                    'limit' => null,
                    'null' => true,
                    'default' => $this->values['null'],
                    'precision' => $this->values['precision'],
                    'comment' => $this->values['comment'],
                ],
            ],
            'updated' => [
                'columnType' => $this->types['timestamp'],
                'options' => [
                    'limit' => null,
                    'null' => true,
                    'default' => $this->values['null'],
                    'precision' => $this->values['precision'],
                    'comment' => $this->values['comment'],
                ],
            ],
        ], $this->helper->columns('users'));
    }

    /**
     * @covers \Migrations\View\Helper\MigrationHelper::column()
     */
    public function testColumn()
    {
        $tableSchema = $this->collection->describe('users');

        $result = $this->helper->column($tableSchema, 'id');
        unset($result['options']['limit']);
        $this->assertEquals([
            'columnType' => 'integer',
            'options' => [
                'null' => false,
                'default' => $this->values['integerNull'],
                'precision' => null,
                'comment' => $this->values['comment'],
                'signed' => true,
                'autoIncrement' => true,
            ],
        ], $result);

        $this->assertEquals([
            'columnType' => 'string',
            'options' => [
                'limit' => 256,
                'null' => true,
                'default' => $this->values['null'],
                'precision' => null,
                'comment' => $this->values['comment'],
            ],
        ], $this->helper->column($tableSchema, 'username'));

        $this->assertEquals([
            'columnType' => 'string',
            'options' => [
                'limit' => 256,
                'null' => true,
                'default' => $this->values['null'],
                'precision' => null,
                'comment' => $this->values['comment'],
            ],
        ], $this->helper->column($tableSchema, 'password'));

        $this->assertEquals([
            'columnType' => $this->types['timestamp'],
            'options' => [
                'limit' => null,
                'null' => true,
                'default' => $this->values['null'],
                'precision' => $this->values['precision'],
                'comment' => $this->values['comment'],
            ],
        ], $this->helper->column($tableSchema, 'created'));

        $this->assertEquals([
            'columnType' => $this->types['timestamp'],
            'options' => [
                'limit' => null,
                'null' => true,
                'default' => $this->values['null'],
                'precision' => $this->values['precision'],
                'comment' => $this->values['comment'],
            ],
        ], $this->helper->column($tableSchema, 'updated'));
    }

    /**
     * @covers \Migrations\View\Helper\MigrationHelper::value()
     */
    public function testValue()
    {
        $this->assertSame('null', $this->helper->value(null));
        $this->assertSame('null', $this->helper->value('null'));
        $this->assertSame('true', $this->helper->value(true));
        $this->assertSame('false', $this->helper->value(false));
        $this->assertSame(1.0, $this->helper->value(1));
        $this->assertSame(-1.0, $this->helper->value(-1));
        $this->assertSame(1.5, $this->helper->value(1.5));
        $this->assertSame(1.5, $this->helper->value('1.5'));
        $this->assertSame(1.0, $this->helper->value('1'));
        $this->assertIsFloat($this->helper->value('1'));
        $this->assertIsString($this->helper->value('1', true));
        $this->assertIsString($this->helper->value('1.5', true));
        $this->assertIsString($this->helper->value(1, true));
        $this->assertIsString($this->helper->value(1.5, true));
        $this->assertSame("'one'", $this->helper->value('one'));
        $this->assertSame("'o\\\"ne'", $this->helper->value('o"ne'));
    }

    /**
     * @covers \Migrations\View\Helper\MigrationHelper::attributes()
     */
    public function testAttributes()
    {
        $result = $this->helper->attributes('users', 'id');
        unset($result['limit']);

        $this->assertEquals([
            'null' => false,
            'default' => $this->values['integerNull'],
            'precision' => null,
            'comment' => $this->values['comment'],
            'signed' => true,
            'autoIncrement' => true,
        ], $result);

        $this->assertEquals([
            'limit' => 256,
            'null' => true,
            'default' => $this->values['null'],
            'precision' => null,
            'comment' => $this->values['comment'],
        ], $this->helper->attributes('users', 'username'));

        $this->assertEquals([
            'limit' => 256,
            'null' => true,
            'default' => $this->values['null'],
            'precision' => null,
            'comment' => $this->values['comment'],
        ], $this->helper->attributes('users', 'password'));

        $this->assertEquals([
            'limit' => null,
            'null' => true,
            'default' => $this->values['null'],
            'precision' => $this->values['precision'],
            'comment' => $this->values['comment'],
        ], $this->helper->attributes('users', 'created'));

        $this->assertEquals([
            'limit' => null,
            'null' => true,
            'default' => $this->values['null'],
            'precision' => $this->values['precision'],
            'comment' => null,
        ], $this->helper->attributes('users', 'updated'));

        $result = $this->helper->attributes('special_tags', 'article_id');
        // Remove as it is inconsistent between dbs and CI/local.
        unset($result['limit']);

        $this->assertEquals([
            'null' => false,
            'default' => $this->values['integerNull'],
            'precision' => null,
            'comment' => $this->values['comment'],
            'signed' => true,
            'autoIncrement' => null,
        ], $result);
    }

    /**
     * @covers \Migrations\View\Helper\MigrationHelper::stringifyList()
     */
    public function testStringifyList()
    {
        $this->assertSame('', $this->helper->stringifyList([]));
        $this->assertSame("
        'key' => 'value',
    ", $this->helper->stringifyList([
            'key' => 'value',
        ]));
        $this->assertSame("
        'key' => 'value',
        'other_key' => 'other_value',
    ", $this->helper->stringifyList([
            'key' => 'value',
            'other_key' => 'other_value',
        ]));
        $this->assertSame("
        'key' => 'value',
        'other_key' => [
            'key' => 'value',
            'other_key' => 'other_value',
        ],
    ", $this->helper->stringifyList([
            'key' => 'value',
            'other_key' => [
                'key' => 'value',
                'other_key' => 'other_value',
            ],
        ]));
    }
}
