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
namespace Migrations\Test\Util;

use Cake\TestSuite\TestCase;
use Migrations\Util\ColumnParser;

/**
 * Tests the ColumnParser
 *
 * @covers \Migrations\Util\ColumnParser
 */
class ColumnParserTest extends TestCase
{
    /**
     * Setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->columnParser = new ColumnParser();
    }

    /**
     * @covers \Migrations\Util\ColumnParser::parseFields
     */
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

    /**
     * @covers \Migrations\Util\ColumnParser::parseIndexes
     */
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

    /**
     * @covers \Migrations\Util\ColumnParser::parsePrimaryKey
     */
    public function testParsePrimaryKey()
    {
        $this->assertEquals(['id'], $this->columnParser->parsePrimaryKey(['id:primary']));
        $this->assertEquals(['id'], $this->columnParser->parsePrimaryKey(['id:integer:primary']));
        $this->assertEquals(['id'], $this->columnParser->parsePrimaryKey(['id:integer:primary:ID_INDEX']));
        $this->assertEquals(
            ['id', 'name'],
            $this->columnParser->parsePrimaryKey(['id:integer:primary', 'name:primary_key'])
        );
    }

    /**
     * @covers \Migrations\Util\ColumnParser::validArguments
     */
    public function testValidArguments()
    {
        $this->assertEquals(
            ['id'],
            $this->columnParser->validArguments(['id'])
        );
        $this->assertEquals(
            ['id', 'id'],
            $this->columnParser->validArguments(['id', 'id'])
        );
        $this->assertEquals(
            ['id:primary_key'],
            $this->columnParser->validArguments(['id:primary_key'])
        );
        $this->assertEquals(
            ['id:primary_key:primary'],
            $this->columnParser->validArguments(['id:primary_key:primary'])
        );
        $this->assertEquals(
            ['id:integer:primary'],
            $this->columnParser->validArguments(['id:integer:primary'])
        );
        $this->assertEquals(
            ['id:integer:primary:ID_INDEX'],
            $this->columnParser->validArguments(['id:integer:primary:ID_INDEX'])
        );
        $this->assertEquals(
            ['id', 'field:string:unique'],
            $this->columnParser->validArguments(['id', 'field:string:unique'])
        );
        $this->assertEquals(
            ['field:fieldType:indexType:indexName'],
            $this->columnParser->validArguments(['field:fieldType:indexType:indexName'])
        );
        $this->assertEquals(
            ['field:fieldType[128]:indexType:indexName'],
            $this->columnParser->validArguments(['field:fieldType[128]:indexType:indexName'])
        );
        $this->assertEquals(
            ['field:integer[9]:indexType:indexName'],
            $this->columnParser->validArguments(['field:integer[9]:indexType:indexName'])
        );
        $this->assertEquals(
            ['field:string?[50]:indexType:indexName'],
            $this->columnParser->validArguments(['field:string?[50]:indexType:indexName'])
        );
        $this->assertEquals(
            ['field:biginteger[18]:indexType:indexName'],
            $this->columnParser->validArguments(['field:biginteger[18]:indexType:indexName'])
        );
    }

    /**
     * @covers \Migrations\Util\ColumnParser::getType
     */
    public function testGetType()
    {
        $this->assertEquals('integer', $this->columnParser->getType('id', null));
        $this->assertEquals('integer', $this->columnParser->getType('id', 'primary_key'));
        $this->assertEquals('integer', $this->columnParser->getType('id', 'integer'));
        $this->assertEquals('integer', $this->columnParser->getType('id', 'other'));
        $this->assertEquals('uuid', $this->columnParser->getType('id', 'uuid'));
        $this->assertEquals('uuid', $this->columnParser->getType('created', 'uuid'));
        $this->assertEquals('datetime', $this->columnParser->getType('created', null));
        $this->assertEquals('datetime', $this->columnParser->getType('modified', null));
        $this->assertEquals('datetime', $this->columnParser->getType('updated', null));
        $this->assertEquals('datetime', $this->columnParser->getType('created_at', null));
        $this->assertEquals('datetime', $this->columnParser->getType('deleted_at', null));
        $this->assertEquals('datetime', $this->columnParser->getType('changed_at', null));
        $this->assertEquals('string', $this->columnParser->getType('some_field', null));
        $this->assertEquals('string', $this->columnParser->getType('some_field', 'string'));
        $this->assertEquals('boolean', $this->columnParser->getType('field', 'boolean'));
        $this->assertEquals('polygon', $this->columnParser->getType('field', 'polygon'));
        $this->assertEquals('decimal', $this->columnParser->getType('latitude', null));
        $this->assertEquals('decimal', $this->columnParser->getType('longitude', null));
    }

    /**
     * @covers \Migrations\Util\ColumnParser::getTypeAndLength
     */
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

    /**
     * @covers \Migrations\Util\ColumnParser::getLength
     */
    public function testGetLength()
    {
        $this->assertEquals(255, $this->columnParser->getLength('string'));
        $this->assertEquals(11, $this->columnParser->getLength('integer'));
        $this->assertEquals(20, $this->columnParser->getLength('biginteger'));
        $this->assertEquals([10, 6], $this->columnParser->getLength('decimal'));
        $this->assertNull($this->columnParser->getLength('text'));
    }

    /**
     * @covers \Migrations\Util\ColumnParser::getIndexName
     */
    public function testGetIndexName()
    {
        $this->assertEquals('SOME_INDEX', $this->columnParser->getIndexName('id', null, 'SOME_INDEX', true));
        $this->assertEquals('SOME_INDEX', $this->columnParser->getIndexName('id', null, 'SOME_INDEX', false));
        $this->assertEquals('SOME_INDEX', $this->columnParser->getIndexName('id', 'primary', 'SOME_INDEX', false));
        $this->assertEquals('SOME_INDEX', $this->columnParser->getIndexName('id', 'primary', 'SOME_INDEX', true));

        $this->assertEquals('UNIQUE_ID', $this->columnParser->getIndexName('id', null, null, true));
        $this->assertEquals('BY_ID', $this->columnParser->getIndexName('id', null, null, false));
        $this->assertEquals('PRIMARY', $this->columnParser->getIndexName('id', 'primary', null, false));
        $this->assertEquals('PRIMARY', $this->columnParser->getIndexName('id', 'primary', null, true));
    }
}
