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

use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Sqlserver;
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
    protected array $fixtures = [
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
            'connection' => $this->connection,
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

        if (getenv('DB') === 'sqlserver') {
            $this->values = [
                'null' => null,
                'integerLimit' => null,
                'integerNull' => null,
                'comment' => null,
                'precision' => 7,
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

    public function testTableMethod()
    {
        $this->assertSame('drop', $this->helper->tableMethod('drop_table'));
        $this->assertSame('create', $this->helper->tableMethod('create_table'));
        $this->assertSame('update', $this->helper->tableMethod('other_method'));
    }

    public function testIndexMethod()
    {
        $this->assertSame('removeIndex', $this->helper->indexMethod('drop_field'));
        $this->assertSame('addIndex', $this->helper->indexMethod('add_field'));
        $this->assertSame('addIndex', $this->helper->indexMethod('alter_field'));
    }

    public function testColumnMethod()
    {
        $this->assertSame('removeColumn', $this->helper->columnMethod('drop_field'));
        $this->assertSame('addColumn', $this->helper->columnMethod('add_field'));
        $this->assertSame('changeColumn', $this->helper->columnMethod('alter_field'));
    }

    public function testColumns()
    {
        $extra = [];
        if ($this->connection->getDriver() instanceof Sqlserver) {
            $extra = ['collate' => 'SQL_Latin1_General_CP1_CI_AS'];
        }
        $this->assertEquals([
            'username' => [
                'columnType' => 'string',
                'options' => [
                    'limit' => 256,
                    'null' => true,
                    'default' => $this->values['null'],
                    'precision' => null,
                    'comment' => $this->values['comment'],
                ] + $extra,
            ],
            'password' => [
                'columnType' => 'string',
                'options' => [
                    'limit' => 256,
                    'null' => true,
                    'default' => $this->values['null'],
                    'precision' => null,
                    'comment' => $this->values['comment'],
                ] + $extra,
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

    public function testColumn()
    {
        $tableSchema = $this->collection->describe('users');

        $options = [
            'null' => false,
            'default' => $this->values['integerNull'],
            'precision' => null,
            'comment' => $this->values['comment'],
            'autoIncrement' => true,
        ];
        if ($this->connection->getDriver() instanceof Mysql) {
            $options['signed'] = false;
        }

        $result = $this->helper->column($tableSchema, 'id');
        unset($result['options']['limit']);
        $this->assertEquals([
            'columnType' => 'integer',
            'options' => $options,
        ], $result);

        $extra = [];
        if ($this->connection->getDriver() instanceof Sqlserver) {
            $extra = ['collate' => 'SQL_Latin1_General_CP1_CI_AS'];
        }
        $this->assertEquals([
            'columnType' => 'string',
            'options' => [
                'limit' => 256,
                'null' => true,
                'default' => $this->values['null'],
                'precision' => null,
                'comment' => $this->values['comment'],
            ] + $extra,
        ], $this->helper->column($tableSchema, 'username'));

        $this->assertEquals([
            'columnType' => 'string',
            'options' => [
                'limit' => 256,
                'null' => true,
                'default' => $this->values['null'],
                'precision' => null,
                'comment' => $this->values['comment'],
            ] + $extra,
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

    public function testAttributes()
    {
        $attributes = [
            'null' => false,
            'default' => $this->values['integerNull'],
            'precision' => null,
            'comment' => $this->values['comment'],
            'autoIncrement' => true,
        ];
        if ($this->connection->getDriver() instanceof Mysql) {
            $attributes['signed'] = false;
        }

        $result = $this->helper->attributes('users', 'id');
        unset($result['limit']);
        $this->assertEquals($attributes, $result);

        $extra = [];
        if ($this->connection->getDriver() instanceof Sqlserver) {
            $extra = ['collate' => 'SQL_Latin1_General_CP1_CI_AS'];
        }

        $this->assertEquals([
            'limit' => 256,
            'null' => true,
            'default' => $this->values['null'],
            'precision' => null,
            'comment' => $this->values['comment'],
        ] + $extra, $this->helper->attributes('users', 'username'));

        $this->assertEquals([
            'limit' => 256,
            'null' => true,
            'default' => $this->values['null'],
            'precision' => null,
            'comment' => $this->values['comment'],
        ] + $extra, $this->helper->attributes('users', 'password'));

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

        $attributes = [
            'null' => false,
            'default' => $this->values['integerNull'],
            'precision' => null,
            'comment' => $this->values['comment'],
            'autoIncrement' => null,
        ];
        if ($this->connection->getDriver() instanceof Mysql) {
            $attributes['signed'] = false;
        }

        $result = $this->helper->attributes('special_tags', 'article_id');
        // Remove as it is inconsistent between dbs and CI/local.
        unset($result['limit']);

        $this->assertEquals($attributes, $result);
    }

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
