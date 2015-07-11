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

use Cake\Database\Schema\Collection;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Migrations\Migrations;

/**
 * Tests the Migrations class
 */
class MigrationsTest extends TestCase
{

    /**
     * Instance of a Migrations object
     *
     * @var \Migrations\Migrations
     */
    public $migrations;

    /**
     * Instance of a Cake Connection object
     *
     * @var \Cake\Database\Connection
     */
    protected $Connection;

    /**
     * Setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->migrations = new Migrations([
            'connection' => 'test'
        ]);

        $this->Connection = ConnectionManager::get('test');
        $tables = (new Collection($this->Connection))->listTables();

        foreach(['phinxlog', 'numbers'] as $tableName) {
            if (in_array($tableName, $tables)) {
                $ormTable = TableRegistry::get($tableName, ['connection' => $this->Connection]);
                $this->Connection->execute(
                    $this->Connection->driver()->schemaDialect()->dropTableSql($ormTable->schema())[0]
                );
            }
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
        unset($this->Connection, $this->migrations);
    }

    /**
     * Tests the status method
     *
     * @return void
     */
    public function testStatus()
    {
        $result = $this->migrations->status();

        $expected = [
            [
                'status' => 'down',
                'id' => '20150416223600',
                'name' => 'MarkMigratedTest'
            ],
            [
                'status' => 'down',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * Tests the migrations and rollbacks
     *
     * @return void
     */
    public function testMigrateAndRollback()
    {
        // Migrate all
        $migrate = $this->migrations->migrate();
        $this->assertTrue($migrate);

        $status = $this->migrations->status();
        $expectedStatus = [
            [
                'status' => 'up',
                'id' => '20150416223600',
                'name' => 'MarkMigratedTest'
            ],
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable'
            ]
        ];
        $this->assertEquals($expectedStatus, $status);

        $table = TableRegistry::get('Numbers', ['connection' => $this->Connection]);
        $columns = $table->schema()->columns();
        $expected = ['id', 'number'];
        $this->assertEquals($columns, $expected);

        // Rollback last
        $rollback = $this->migrations->rollback();
        $this->assertTrue($rollback);
        $expectedStatus[1]['status'] = 'down';
        $status = $this->migrations->status();
        $this->assertEquals($expectedStatus, $status);

        // Migrate all again and rollback all
        $this->migrations->migrate();
        $rollback = $this->migrations->rollback(['target' => 0]);
        $this->assertTrue($rollback);
        $expectedStatus[0]['status'] = 'down';
        $status = $this->migrations->status();
        $this->assertEquals($expectedStatus, $status);
    }

    /**
     * Tests that migrate returns false in case of error
     * and can return a error message
     *
     * @return void
     */
    public function testMigrateErrors()
    {
        $this->migrations->migrate();

        $tables = (new Collection($this->Connection))->listTables();
        if (in_array('phinxlog', $tables)) {
            $ormTable = TableRegistry::get('phinxlog', ['connection' => $this->Connection]);
            $this->Connection->execute(
                $this->Connection->driver()->schemaDialect()->truncateTableSql($ormTable->schema())[0]
            );
        }

        $migrate = $this->migrations->migrate();
        $this->assertFalse($migrate);

        $error = $this->migrations->getLastError();
        $this->assertNotNull($error);
    }

    /**
     * Tests that rollback returns false in case of error
     * and can return a error message
     *
     * @return void
     */
    public function testRollbackErrors()
    {
        $this->migrations->migrate();
        $this->migrations->rollback(['target' => 0]);

        $this->Connection->newQuery()
            ->insert(['version', 'start_time', 'end_time'])
            ->values([
                'version' => 20150416223600,
                'start_time' => date('Y-d-m', time()),
                'end_time' => date('Y-d-m', time())
            ])
            ->values([
                'version' => 20150704160200,
                'start_time' => date('Y-d-m', time()),
                'end_time' => date('Y-d-m', time())
            ])
            ->into('phinxlog')
            ->execute();

        $rollback = $this->migrations->rollback();
        $this->assertFalse($rollback);
        $error = $this->migrations->getLastError();
        $this->assertNotNull($error);
    }
}
