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
namespace Migrations\Test\TestCase\Util;

use Cake\TestSuite\TestCase;
use Migrations\Util\ColumnParser;

/**
 * Tests the ColumnParser
 */
class ColumnParserTest extends TestCase
{
    /**
     * @var \Migrations\Util\ColumnParser
     */
    protected $columnParser;

    /**
     * Setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->columnParser = new ColumnParser();
    }

    public function testParseFields()
    {
        $this->assertEquals([
            'id' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 11,
                ],
            ],
        ], $this->columnParser->parseFields(['id']));

        $this->assertEquals([
            'id' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 11,
                    'autoIncrement' => true,
                ],
            ],
        ], $this->columnParser->parseFields(['id:primary']));

        $this->assertEquals([
            'id' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 11,
                ],
            ],
            'name' => [
                'columnType' => 'string',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 255,
                ],
            ],
        ], $this->columnParser->parseFields(['id', 'name']));

        $this->assertEquals([
            'id' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 11,
                ],
            ],
            'created' => [
                'columnType' => 'datetime',
                'options' => [
                    'null' => false,
                    'default' => null,
                ],
            ],
            'modified' => [
                'columnType' => 'datetime',
                'options' => [
                    'null' => false,
                    'default' => null,
                ],
            ],
            'updated' => [
                'columnType' => 'datetime',
                'options' => [
                    'null' => false,
                    'default' => null,
                ],
            ],
            'deleted_at' => [
                'columnType' => 'datetime',
                'options' => [
                    'null' => false,
                    'default' => null,
                ],
            ],
            'latitude' => [
                'columnType' => 'decimal',
                'options' => [
                    'default' => false,
                    'null' => false,
                    'precision' => 10,
                    'scale' => 6,
                ],
            ],
            'longitude' => [
                'columnType' => 'decimal',
                'options' => [
                    'default' => false,
                    'null' => false,
                    'precision' => 10,
                    'scale' => 6,
                ],
            ],
        ], $this->columnParser->parseFields(['id', 'created', 'modified', 'updated', 'deleted_at', 'latitude', 'longitude']));

        $expected = [
            'id' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 11,
                ],
            ],
            'name' => [
                'columnType' => 'string',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 255,
                ],
            ],
            'description' => [
                'columnType' => 'string',
                'options' => [
                    'null' => true,
                    'default' => null,
                    'limit' => 255,
                ],
            ],
            'age' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => true,
                    'default' => null,
                    'limit' => 11,
                ],
            ],
            'amount' => [
                'columnType' => 'decimal',
                'options' => [
                    'null' => true,
                    'default' => null,
                    'precision' => 6,
                    'scale' => 3,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(['id', 'name:string', 'description:string?', 'age:integer?', 'amount:decimal?[6,3]']);
        $this->assertEquals($expected, $actual);

        $expected = [
            'id' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 11,
                ],
            ],
            'name' => [
                'columnType' => 'string',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 125,
                ],
            ],
            'description' => [
                'columnType' => 'string',
                'options' => [
                    'null' => true,
                    'default' => null,
                    'limit' => 50,
                ],
            ],
            'age' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => true,
                    'default' => null,
                    'limit' => 11,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields([
            'id',
            'name:string[125]',
            'description:string?[50]',
            'age:integer?',
        ]);
        $this->assertEquals($expected, $actual);
    }

    public function testParseIndexes()
    {
        $this->assertEquals(['UNIQUE_ID' => [
            'columns' => ['id'],
            'options' => ['unique' => true, 'name' => 'UNIQUE_ID'],
        ]], $this->columnParser->parseIndexes(['id:integer:unique']));
        $this->assertEquals(['UNIQUE_USER' => [
            'columns' => ['email'],
            'options' => ['unique' => true, 'name' => 'UNIQUE_USER'],
        ]], $this->columnParser->parseIndexes(['email:string:unique:UNIQUE_USER']));
        $this->assertEquals(['UNIQUE_EVENT' => [
            'columns' => ['event_id', 'market_id'],
            'options' => ['unique' => true, 'name' => 'UNIQUE_EVENT'],
        ]], $this->columnParser->parseIndexes([
            'some_field',
            'event_id:integer:unique:UNIQUE_EVENT',
            'market_id:integer:unique:UNIQUE_EVENT',
        ]));
    }

    public function testParsePrimaryKey()
    {
        $this->assertEquals(['id'], $this->columnParser->parsePrimaryKey(['id:primary']));
        $this->assertEquals(['id'], $this->columnParser->parsePrimaryKey(['id:integer:primary']));
        $this->assertEquals(['id'], $this->columnParser->parsePrimaryKey(['id:integer:primary:ID_INDEX']));
        $this->assertEquals(
            ['id', 'name'],
            $this->columnParser->parsePrimaryKey(['id:integer:primary', 'name:primary_key']),
        );
    }

    public function testValidArguments()
    {
        $this->assertEquals(
            ['id'],
            $this->columnParser->validArguments(['id']),
        );
        $this->assertEquals(
            ['id', 'id'],
            $this->columnParser->validArguments(['id', 'id']),
        );
        $this->assertEquals(
            ['id:primary_key'],
            $this->columnParser->validArguments(['id:primary_key']),
        );
        $this->assertEquals(
            ['id:primary_key:primary'],
            $this->columnParser->validArguments(['id:primary_key:primary']),
        );
        $this->assertEquals(
            ['id:integer:primary'],
            $this->columnParser->validArguments(['id:integer:primary']),
        );
        $this->assertEquals(
            ['id:integer:primary:ID_INDEX'],
            $this->columnParser->validArguments(['id:integer:primary:ID_INDEX']),
        );
        $this->assertEquals(
            ['id', 'field:string:unique'],
            $this->columnParser->validArguments(['id', 'field:string:unique']),
        );
        $this->assertEquals(
            ['field:fieldType:indexType:indexName'],
            $this->columnParser->validArguments(['field:fieldType:indexType:indexName']),
        );
        $this->assertEquals(
            ['field:fieldType[128]:indexType:indexName'],
            $this->columnParser->validArguments(['field:fieldType[128]:indexType:indexName']),
        );
        $this->assertEquals(
            ['field:integer[9]:indexType:indexName'],
            $this->columnParser->validArguments(['field:integer[9]:indexType:indexName']),
        );
        $this->assertEquals(
            ['field:string?[50]:indexType:indexName'],
            $this->columnParser->validArguments(['field:string?[50]:indexType:indexName']),
        );
        $this->assertEquals(
            ['field:biginteger[18]:indexType:indexName'],
            $this->columnParser->validArguments(['field:biginteger[18]:indexType:indexName']),
        );
    }

    public function testGetType()
    {
        $this->assertSame('integer', $this->columnParser->getType('id', null));
        $this->assertSame('integer', $this->columnParser->getType('id', 'primary_key'));
        $this->assertSame('integer', $this->columnParser->getType('id', 'integer'));
        $this->assertSame('integer', $this->columnParser->getType('id', 'other'));
        $this->assertSame('uuid', $this->columnParser->getType('id', 'uuid'));
        $this->assertSame('uuid', $this->columnParser->getType('created', 'uuid'));
        $this->assertSame('datetime', $this->columnParser->getType('created', null));
        $this->assertSame('datetime', $this->columnParser->getType('modified', null));
        $this->assertSame('datetime', $this->columnParser->getType('updated', null));
        $this->assertSame('datetime', $this->columnParser->getType('created_at', null));
        $this->assertSame('datetime', $this->columnParser->getType('deleted_at', null));
        $this->assertSame('datetime', $this->columnParser->getType('changed_at', null));
        $this->assertSame('string', $this->columnParser->getType('some_field', null));
        $this->assertSame('string', $this->columnParser->getType('some_field', 'string'));
        $this->assertSame('boolean', $this->columnParser->getType('field', 'boolean'));
        $this->assertSame('polygon', $this->columnParser->getType('field', 'polygon'));
        $this->assertSame('decimal', $this->columnParser->getType('latitude', null));
        $this->assertSame('decimal', $this->columnParser->getType('longitude', null));
    }

    public function testGetTypeAndLength()
    {
        $this->assertEquals(['string', 255], $this->columnParser->getTypeAndLength('name', 'string'));
        $this->assertEquals(['integer', 11], $this->columnParser->getTypeAndLength('counter', 'integer'));
        $this->assertEquals(['string', 128], $this->columnParser->getTypeAndLength('name', 'string[128]'));
        $this->assertEquals(['integer', 9], $this->columnParser->getTypeAndLength('counter', 'integer[9]'));
        $this->assertEquals(['biginteger', 18], $this->columnParser->getTypeAndLength('bigcounter', 'biginteger[18]'));
        $this->assertEquals(['integer', 11], $this->columnParser->getTypeAndLength('id', null));
        $this->assertEquals(['string', 255], $this->columnParser->getTypeAndLength('username', null));
        $this->assertEquals(['datetime', null], $this->columnParser->getTypeAndLength('created', null));
        $this->assertEquals(['datetime', null], $this->columnParser->getTypeAndLength('changed_at', null));
        $this->assertEquals(['decimal', [10, 6]], $this->columnParser->getTypeAndLength('latitude', 'decimal[10,6]'));
    }

    public function testGetTypeAndLengthReturnsIntegerTypes()
    {
        // Test that lengths are returned as integers, not strings
        [, $length] = $this->columnParser->getTypeAndLength('name', 'string[128]');
        $this->assertIsInt($length);
        $this->assertSame(128, $length);

        [, $length] = $this->columnParser->getTypeAndLength('count', 'integer[9]');
        $this->assertIsInt($length);
        $this->assertSame(9, $length);

        // Test that precision/scale arrays contain integers
        [, $length] = $this->columnParser->getTypeAndLength('amount', 'decimal[10,6]');
        $this->assertIsArray($length);
        $this->assertCount(2, $length);
        $this->assertIsInt($length[0]);
        $this->assertIsInt($length[1]);
        $this->assertSame(10, $length[0]);
        $this->assertSame(6, $length[1]);

        // Test default lengths are also integers
        [, $length] = $this->columnParser->getTypeAndLength('name', 'string');
        $this->assertIsInt($length);
        $this->assertSame(255, $length);

        [, $length] = $this->columnParser->getTypeAndLength('id', 'integer');
        $this->assertIsInt($length);
        $this->assertSame(11, $length);
    }

    public function testGetLength()
    {
        $this->assertSame(255, $this->columnParser->getLength('string'));
        $this->assertSame(11, $this->columnParser->getLength('integer'));
        $this->assertSame(20, $this->columnParser->getLength('biginteger'));
        $this->assertEquals([10, 6], $this->columnParser->getLength('decimal'));
        $this->assertNull($this->columnParser->getLength('text'));
    }

    public function testGetIndexName()
    {
        $this->assertSame('SOME_INDEX', $this->columnParser->getIndexName('id', null, 'SOME_INDEX', true));
        $this->assertSame('SOME_INDEX', $this->columnParser->getIndexName('id', null, 'SOME_INDEX', false));
        $this->assertSame('SOME_INDEX', $this->columnParser->getIndexName('id', 'primary', 'SOME_INDEX', false));
        $this->assertSame('SOME_INDEX', $this->columnParser->getIndexName('id', 'primary', 'SOME_INDEX', true));

        $this->assertSame('UNIQUE_ID', $this->columnParser->getIndexName('id', null, null, true));
        $this->assertSame('BY_ID', $this->columnParser->getIndexName('id', null, null, false));
        $this->assertSame('PRIMARY', $this->columnParser->getIndexName('id', 'primary', null, false));
        $this->assertSame('PRIMARY', $this->columnParser->getIndexName('id', 'primary', null, true));
    }

    public function testParseFieldsWithReferences()
    {
        // Test basic references - should convert to integer
        $expected = [
            'user_id' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 11,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(['user_id:references']);
        $this->assertEquals($expected, $actual);

        // Test nullable references
        $expected = [
            'category_id' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => true,
                    'default' => null,
                    'limit' => 11,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(['category_id:references?']);
        $this->assertEquals($expected, $actual);

        // Test references with explicit table name
        $expected = [
            'category_id' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 11,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(['category_id:references:categories']);
        $this->assertEquals($expected, $actual);
    }

    public function testParseFieldsWithDefaultValues()
    {
        // Test boolean default true
        $expected = [
            'active' => [
                'columnType' => 'boolean',
                'options' => [
                    'null' => false,
                    'default' => true,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(['active:boolean:default[true]']);
        $this->assertEquals($expected, $actual);

        // Test boolean default false
        $expected = [
            'skip_updates' => [
                'columnType' => 'boolean',
                'options' => [
                    'null' => false,
                    'default' => false,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(['skip_updates:boolean:default[false]']);
        $this->assertEquals($expected, $actual);

        // Test integer default
        $expected = [
            'count' => [
                'columnType' => 'integer',
                'options' => [
                    'null' => false,
                    'default' => 0,
                    'limit' => 11,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(['count:integer:default[0]']);
        $this->assertEquals($expected, $actual);

        // Test string default with quotes
        $expected = [
            'status' => [
                'columnType' => 'string',
                'options' => [
                    'null' => false,
                    'default' => 'pending',
                    'limit' => 255,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(["status:string:default['pending']"]);
        $this->assertEquals($expected, $actual);

        // Test nullable with default
        $expected = [
            'role' => [
                'columnType' => 'string',
                'options' => [
                    'null' => true,
                    'default' => 'user',
                    'limit' => 255,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(["role:string?:default['user']"]);
        $this->assertEquals($expected, $actual);

        // Test default with index
        $expected = [
            'email' => [
                'columnType' => 'string',
                'options' => [
                    'null' => false,
                    'default' => null,
                    'limit' => 255,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(['email:string:default[null]:unique']);
        $this->assertEquals($expected, $actual);

        // Test float default
        $expected = [
            'rate' => [
                'columnType' => 'decimal',
                'options' => [
                    'null' => false,
                    'default' => 1.5,
                    'precision' => 10,
                    'scale' => 6,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(['rate:decimal:default[1.5]']);
        $this->assertEquals($expected, $actual);

        // Test length with default
        $expected = [
            'code' => [
                'columnType' => 'string',
                'options' => [
                    'null' => false,
                    'default' => 'ABC',
                    'limit' => 10,
                ],
            ],
        ];
        $actual = $this->columnParser->parseFields(["code:string[10]:default['ABC']"]);
        $this->assertEquals($expected, $actual);
    }

    public function testParseDefaultValue()
    {
        // Test null and empty values
        $this->assertNull($this->columnParser->parseDefaultValue(null, 'string'));
        $this->assertNull($this->columnParser->parseDefaultValue('', 'string'));
        $this->assertNull($this->columnParser->parseDefaultValue('null', 'string'));
        $this->assertNull($this->columnParser->parseDefaultValue('NULL', 'string'));

        // Test boolean values
        $this->assertTrue($this->columnParser->parseDefaultValue('true', 'boolean'));
        $this->assertTrue($this->columnParser->parseDefaultValue('TRUE', 'boolean'));
        $this->assertFalse($this->columnParser->parseDefaultValue('false', 'boolean'));
        $this->assertFalse($this->columnParser->parseDefaultValue('FALSE', 'boolean'));

        // Test integer values
        $this->assertSame(0, $this->columnParser->parseDefaultValue('0', 'integer'));
        $this->assertSame(123, $this->columnParser->parseDefaultValue('123', 'integer'));
        $this->assertSame(-456, $this->columnParser->parseDefaultValue('-456', 'integer'));

        // Test float values
        $this->assertSame(1.5, $this->columnParser->parseDefaultValue('1.5', 'decimal'));
        $this->assertSame(-2.75, $this->columnParser->parseDefaultValue('-2.75', 'decimal'));

        // Test quoted strings
        $this->assertSame('hello', $this->columnParser->parseDefaultValue("'hello'", 'string'));
        $this->assertSame('world', $this->columnParser->parseDefaultValue('"world"', 'string'));

        // Test SQL expressions (returned as-is)
        $this->assertSame('CURRENT_TIMESTAMP', $this->columnParser->parseDefaultValue('CURRENT_TIMESTAMP', 'datetime'));
    }

    public function testParseIndexesWithDefaultValues()
    {
        // Ensure indexes still work with default values in the definition
        $expected = [
            'UNIQUE_EMAIL' => [
                'columns' => ['email'],
                'options' => ['unique' => true, 'name' => 'UNIQUE_EMAIL'],
            ],
        ];
        $actual = $this->columnParser->parseIndexes(['email:string:default[null]:unique']);
        $this->assertEquals($expected, $actual);

        // Test with custom index name
        $expected = [
            'IDX_COUNT' => [
                'columns' => ['count'],
                'options' => ['unique' => false, 'name' => 'IDX_COUNT'],
            ],
        ];
        $actual = $this->columnParser->parseIndexes(['count:integer:default[0]:index:IDX_COUNT']);
        $this->assertEquals($expected, $actual);
    }

    public function testValidArgumentsWithDefaultValues()
    {
        $this->assertEquals(
            ['active:boolean:default[true]'],
            $this->columnParser->validArguments(['active:boolean:default[true]']),
        );
        $this->assertEquals(
            ['count:integer:default[0]:unique'],
            $this->columnParser->validArguments(['count:integer:default[0]:unique']),
        );
        $this->assertEquals(
            ["status:string:default['pending']:index:IDX_STATUS"],
            $this->columnParser->validArguments(["status:string:default['pending']:index:IDX_STATUS"]),
        );
    }

    public function testParseForeignKeys()
    {
        // Test basic reference - infer table name from field
        $expected = [
            'fk_user_id' => [
                'type' => 'foreign',
                'columns' => ['user_id'],
                'references' => ['users', 'id'],
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ],
        ];
        $actual = $this->columnParser->parseForeignKeys(['user_id:references']);
        $this->assertEquals($expected, $actual);

        // Test reference with explicit table name
        $expected = [
            'fk_category_id' => [
                'type' => 'foreign',
                'columns' => ['category_id'],
                'references' => ['custom_categories', 'id'],
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ],
        ];
        $actual = $this->columnParser->parseForeignKeys(['category_id:references:custom_categories']);
        $this->assertEquals($expected, $actual);

        // Test reference with custom constraint name
        $expected = [
            'custom_fk' => [
                'type' => 'foreign',
                'columns' => ['author_id'],
                'references' => ['authors', 'id'],
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ],
        ];
        $actual = $this->columnParser->parseForeignKeys(['author_id:references:authors:custom_fk']);
        $this->assertEquals($expected, $actual);

        // Test multiple foreign keys
        $expected = [
            'fk_user_id' => [
                'type' => 'foreign',
                'columns' => ['user_id'],
                'references' => ['users', 'id'],
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ],
            'fk_category_id' => [
                'type' => 'foreign',
                'columns' => ['category_id'],
                'references' => ['categories', 'id'],
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ],
        ];
        $actual = $this->columnParser->parseForeignKeys(['user_id:references', 'category_id:references']);
        $this->assertEquals($expected, $actual);

        // Test that non-reference fields are ignored
        $expected = [];
        $actual = $this->columnParser->parseForeignKeys(['name:string', 'age:integer']);
        $this->assertEquals($expected, $actual);
    }
}
