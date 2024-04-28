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

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\Util\TableFinder;

class TableFinderTest extends TestCase
{
    public function testGetTableNames(): void
    {
        $this->loadPlugins(['TestBlog']);
        $finder = new TableFinder('test');

        $result = $finder->getTableNames('TestBlog');
        $this->assertContains('articles', $result);
        $this->assertContains('categories', $result);
        $this->assertContains('dogs', $result);
        $this->assertContains('parts', $result);
    }

    /**
     * Test that using fetchTableName in a Table object class
     * where the table name is composed with the database name (e.g. mydb.mytable)
     * will return:
     *
     * - only the table name if the current connection `database` parameter is the first part
     *   of the table name
     * - the full string (e.g. mydb.mytable) if the current connection `database` parameter
     *   is not the first part of the table name
     */
    public function testFetchTableNames(): void
    {
        $finder = new TableFinder('test');
        $expected = ['alternative.special_tags'];
        $this->assertEquals($expected, $finder->fetchTableName('SpecialTagsTable.php', 'TestBlog'));

        ConnectionManager::setConfig('alternative', [
            'database' => 'alternative',
        ]);
        $finder = new TableFinder('alternative');
        $expected = ['special_tags'];
        $this->assertEquals($expected, $finder->fetchTableName('SpecialTagsTable.php', 'TestBlog'));

        ConnectionManager::drop('alternative');
        ConnectionManager::setConfig('alternative', [
            'schema' => 'alternative',
        ]);
        $finder = new TableFinder('alternative');
        $expected = ['special_tags'];
        $this->assertEquals($expected, $finder->fetchTableName('SpecialTagsTable.php', 'TestBlog'));
    }
}
