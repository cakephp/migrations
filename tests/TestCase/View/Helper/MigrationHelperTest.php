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

use Cake\Core\Configure;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Sqlserver;
use Cake\Database\Schema\Collection;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use Migrations\Db\Adapter\MysqlAdapter;
use Migrations\View\Helper\MigrationHelper;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ConfigurationTrait
 *
 * Note: This test must run in a separate process because adapter tests earlier
 * in the test suite drop and recreate the database, destroying the schema.
 */
#[RunTestsInSeparateProcesses]
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
    protected function setUp(): void
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
            $this->types = [
                'timestamp' => 'timestampfractional',
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
            $this->types = [
                'timestamp' => 'datetimefractional',
            ];
        }
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->helper, $this->view, $this->collection, $this->connection);
    }

    public function testTableMethod(): void
    {
        $this->assertSame('drop', $this->helper->tableMethod('drop_table'));
        $this->assertSame('create', $this->helper->tableMethod('create_table'));
        $this->assertSame('update', $this->helper->tableMethod('other_method'));
    }

    public function testIndexMethod(): void
    {
        $this->assertSame('removeIndex', $this->helper->indexMethod('drop_field'));
        $this->assertSame('addIndex', $this->helper->indexMethod('add_field'));
        $this->assertSame('addIndex', $this->helper->indexMethod('alter_field'));
    }

    public function testColumnMethod(): void
    {
        $this->assertSame('removeColumn', $this->helper->columnMethod('drop_field'));
        $this->assertSame('addColumn', $this->helper->columnMethod('add_field'));
        $this->assertSame('changeColumn', $this->helper->columnMethod('alter_field'));
    }

    public function testColumns(): void
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

    public function testColumn(): void
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

    public function testValue(): void
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

    public function testAttributes(): void
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

    public function testStringifyList(): void
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

    /**
     * Test that getColumnOption removes null collate for all databases
     *
     * @see https://github.com/cakephp/migrations/issues/974
     */
    public function testGetColumnOptionRemovesNullCollate(): void
    {
        $options = [
            'length' => 255,
            'null' => true,
            'default' => null,
            'collate' => null,
        ];

        $result = $this->helper->getColumnOption($options);

        // collate => null should NOT be in the output for any database
        // because it causes "collate is not a valid column option" error
        $this->assertArrayNotHasKey('collate', $result, 'collate => null should be removed');
        $this->assertArrayNotHasKey('collation', $result, 'collation should not be set when collate is null');
    }

    /**
     * Test that getColumnOption converts collate to collation for all databases
     *
     * Phinx uses 'collation' not 'collate', so this must be converted for any database
     * that supports per-column collation (MySQL, SQL Server, PostgreSQL, SQLite).
     *
     * @see https://github.com/cakephp/migrations/issues/974
     */
    public function testGetColumnOptionConvertsCollateToCollation(): void
    {
        $options = [
            'length' => 255,
            'null' => true,
            'default' => null,
            'collate' => 'en_US.UTF-8',
        ];

        $result = $this->helper->getColumnOption($options);

        // collate should be converted to collation for Phinx compatibility
        // This is a bug: currently only MySQL/SQLServer convert this
        $this->assertArrayNotHasKey('collate', $result, 'collate should be converted to collation');
        $this->assertArrayHasKey('collation', $result, 'collation should be set from collate value');
        $this->assertSame('en_US.UTF-8', $result['collation']);
    }

    /**
     * Test that getColumnOption includes the fixed option for binary columns
     */
    public function testGetColumnOptionIncludesFixed(): void
    {
        $options = [
            'length' => 20,
            'null' => true,
            'default' => null,
            'fixed' => true,
        ];

        $result = $this->helper->getColumnOption($options);

        $this->assertArrayHasKey('fixed', $result);
        $this->assertTrue($result['fixed']);
    }

    /**
     * Test that getColumnOption excludes fixed when not set
     */
    public function testGetColumnOptionExcludesFixedWhenNotSet(): void
    {
        $options = [
            'length' => 20,
            'null' => true,
            'default' => null,
        ];

        $result = $this->helper->getColumnOption($options);

        $this->assertArrayNotHasKey('fixed', $result);
    }

    /**
     * Test that getColumnOption converts CakePHP's LENGTH_LONG to migrations TEXT_LONG
     *
     * CakePHP uses LENGTH_LONG = 4294967295 for LONGTEXT, but migrations expects
     * TEXT_LONG = 2147483647. This ensures generated migrations use the correct value.
     */
    public function testGetColumnOptionConvertsLengthLongToTextLong(): void
    {
        $options = [
            'limit' => TableSchema::LENGTH_LONG, // 4294967295
            'null' => true,
            'default' => null,
        ];

        $result = $this->helper->getColumnOption($options);

        $this->assertArrayHasKey('limit', $result);
        $this->assertSame(MysqlAdapter::TEXT_LONG, $result['limit']); // 2147483647
    }

    /**
     * Test that getColumnOption preserves other limit values unchanged
     */
    public function testGetColumnOptionPreservesOtherLimits(): void
    {
        $options = [
            'limit' => 255, // TEXT_TINY / LENGTH_TINY - same value
            'null' => true,
            'default' => null,
        ];

        $result = $this->helper->getColumnOption($options);

        $this->assertSame(255, $result['limit']);
    }

    /**
     * Test that table options the adapter would apply anyway are not rendered
     */
    public function testTableOptionsOmitsDefaults(): void
    {
        Configure::write('Migrations.default_collation', 'utf8mb4_general_ci');

        $schema = new TableSchema('events');
        $schema->addColumn('id', ['type' => 'integer']);
        $schema->setOptions([
            'collation' => 'utf8mb4_general_ci',
            'engine' => MysqlAdapter::DEFAULT_ENGINE,
        ]);

        $this->assertSame([], $this->helper->tableOptions($schema));

        Configure::delete('Migrations.default_collation');
    }

    /**
     * Test that table options differing from the defaults are rendered
     */
    public function testTableOptionsIncludesNonDefaults(): void
    {
        Configure::write('Migrations.default_collation', 'utf8mb4_general_ci');

        $schema = new TableSchema('events');
        $schema->addColumn('id', ['type' => 'integer']);
        $schema->setOptions([
            'collation' => 'utf8_hungarian_ci',
            'engine' => 'MyISAM',
        ]);

        $this->assertSame(
            ['collation' => 'utf8_hungarian_ci', 'engine' => 'MyISAM'],
            $this->helper->tableOptions($schema),
        );

        Configure::delete('Migrations.default_collation');
    }

    /**
     * Test that drivers reflecting no table options render none
     */
    public function testTableOptionsWithoutReflectedOptions(): void
    {
        $schema = new TableSchema('events');
        $schema->addColumn('id', ['type' => 'integer']);

        $this->assertSame([], $this->helper->tableOptions($schema));
    }

    /**
     * Test that table options are rendered inline, in insertion order
     */
    public function testStringifyTableOptions(): void
    {
        $this->assertSame('', $this->helper->stringifyTableOptions([]));

        $this->assertSame(
            "'id' => false, 'primary_key' => ['id', 'name'], 'collation' => 'utf8_hungarian_ci'",
            $this->helper->stringifyTableOptions([
                'id' => false,
                'primary_key' => ['id', 'name'],
                'collation' => 'utf8_hungarian_ci',
            ]),
        );
    }

    /**
     * Test that quotes in a value cannot break out of the generated statement
     */
    public function testStringifyTableOptionsEscapesQuotes(): void
    {
        $this->assertSame(
            "'primary_key' => ['it\\'s']",
            $this->helper->stringifyTableOptions(['primary_key' => ["it's"]]),
        );
    }
}
