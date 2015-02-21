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
 * @covers Migrations\Util\ColumnParser
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
        $this->columnParser = new ColumnParser;
    }

    /**
     * @covers Migrations\Util\ColumnParser::parseFields
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
        ], $this->columnParser->parseFields(['id', 'created', 'modified', 'updated']));
    }

    /**
     * @covers Migrations\Util\ColumnParser::parseIndexes
     */
    public function testParseIndexes()
    {
        $this->assertEquals(['PRIMARY' => [
            'columns' => ['id'],
            'options' => ['unique' => true, 'name' => 'PRIMARY']
        ]], $this->columnParser->parseIndexes(['id:primary_key']));
        $this->assertEquals(['PRIMARY' => [
            'columns' => ['id'],
            'options' => ['unique' => true, 'name' => 'PRIMARY']
        ]], $this->columnParser->parseIndexes(['id:primary']));
        $this->assertEquals(['PRIMARY' => [
            'columns' => ['id'],
            'options' => ['unique' => true, 'name' => 'PRIMARY']
        ]], $this->columnParser->parseIndexes(['id:integer:primary']));
        $this->assertEquals(['ID_INDEX' => [
            'columns' => ['id'],
            'options' => ['unique' => true, 'name' => 'ID_INDEX']
        ]], $this->columnParser->parseIndexes(['id:integer:primary:ID_INDEX']));
        $this->assertEquals(['UNIQUE_ID' => [
            'columns' => ['id'],
            'options' => ['unique' => true, 'name' => 'UNIQUE_ID']
        ]], $this->columnParser->parseIndexes(['id:integer:unique']));
        $this->assertEquals(['UNIQUE_USER' => [
            'columns' => ['email'],
            'options' => ['unique' => true, 'name' => 'UNIQUE_USER']
        ]], $this->columnParser->parseIndexes(['email:string:unique:UNIQUE_USER']));
        $this->assertEquals(['UNIQUE_EVENT' => [
            'columns' => ['event_id', 'market_id'],
            'options' => ['unique' => true, 'name' => 'UNIQUE_EVENT']
        ]], $this->columnParser->parseIndexes([
            'some_field',
            'event_id:integer:unique:UNIQUE_EVENT',
            'market_id:integer:unique:UNIQUE_EVENT',
        ]));
    }

    /**
     * @covers Migrations\Util\ColumnParser::validArguments
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
    }

    /**
     * @covers Migrations\Util\ColumnParser::getType
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
        $this->assertEquals('string', $this->columnParser->getType('some_field', null));
        $this->assertEquals('string', $this->columnParser->getType('some_field', 'string'));
        $this->assertEquals('boolean', $this->columnParser->getType('field', 'boolean'));
        $this->assertEquals('polygon', $this->columnParser->getType('field', 'polygon'));
    }

    /**
     * @covers Migrations\Util\ColumnParser::getLength
     */
    public function testGetLength()
    {
        $this->assertEquals(255, $this->columnParser->getLength('string'));
        $this->assertEquals(11, $this->columnParser->getLength('integer'));
        $this->assertEquals(20, $this->columnParser->getLength('biginteger'));
        $this->assertNull($this->columnParser->getLength('text'));
    }

    /**
     * @covers Migrations\Util\ColumnParser::getIndexName
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
