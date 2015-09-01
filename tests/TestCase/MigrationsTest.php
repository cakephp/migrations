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

use Cake\Core\Plugin;
use Cake\Database\Schema\Collection;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Migrations\Migrations;
use Phinx\Migration\Util;

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
        $params = [
            'connection' => 'test',
            'source' => 'TestsMigrations'
        ];

        // Get the PDO connection to have the same across the various objects needed to run the tests
        $migrations = new Migrations();
        $input = $migrations->getInput('Migrate', [], $params);
        $migrations->setInput($input);
        $migrations->getManager($migrations->getConfig());
        $this->Connection = ConnectionManager::get('test');
        $connection = $migrations->getManager()->getEnvironment('default')->getAdapter()->getConnection();
        $this->Connection->driver()->connection($connection);

        // Get an instance of the Migrations object on which we will run the tests
        $this->migrations = new Migrations($params);
        $this->migrations
            ->getManager($migrations->getConfig())
            ->getEnvironment('default')
            ->getAdapter()
            ->setConnection($connection);

        $tables = (new Collection($this->Connection))->listTables();
        if (in_array('phinxlog', $tables)) {
            $ormTable = TableRegistry::get('phinxlog', ['connection' => $this->Connection]);
            $query = $this->Connection->driver()->schemaDialect()->truncateTableSql($ormTable->schema());
            $this->Connection->execute(
                $query[0]
            );
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
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable'
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable'
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
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable'
            ],
            [
                'status' => 'up',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable'
            ]
        ];
        $this->assertEquals($expectedStatus, $status);

        $table = TableRegistry::get('Numbers', ['connection' => $this->Connection]);
        $columns = $table->schema()->columns();
        $expected = ['id', 'number', 'radix'];
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
     * @expectedException Exception
     */
    public function testMigrateErrors()
    {
        $this->migrations->markMigrated(20150704160200);
        $this->migrations->migrate();
    }

    /**
     * Tests that rollback returns false in case of error
     * and can return a error message
     *
     * @expectedException Exception
     */
    public function testRollbackErrors()
    {
        $this->migrations->markMigrated(20150704160200);
        $this->migrations->markMigrated(20150724233100);
        $this->migrations->rollback();
    }

    /**
     * Tests that marking migrated a non-existant migrations returns an error
     * and can return a error message
     *
     * @expectedException Exception
     */
    public function testMarkMigratedErrors()
    {
        $this->migrations->markMigrated(20150704000000);
    }

    /**
     * Tests that calling the migrations methods while passing
     * parameters will override the default ones
     *
     * @return void
     */
    public function testOverrideOptions()
    {
        $result = $this->migrations->status();
        $expectedStatus = [
            [
                'status' => 'down',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable'
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable'
            ]
        ];
        $this->assertEquals($expectedStatus, $result);

        $result = $this->migrations->status(['source' => 'Migrations']);
        $expected = [
            [
                'status' => 'down',
                'id' => '20150416223600',
                'name' => 'MarkMigratedTest'
            ]
        ];
        $this->assertEquals($expected, $result);

        $migrate = $this->migrations->migrate(['source' => 'Migrations']);
        $this->assertTrue($migrate);
        $result = $this->migrations->status(['source' => 'Migrations']);
        $expected[0]['status'] = 'up';
        $this->assertEquals($expected, $result);

        $rollback = $this->migrations->rollback(['source' => 'Migrations']);
        $this->assertTrue($rollback);
        $result = $this->migrations->status(['source' => 'Migrations']);
        $expected[0]['status'] = 'down';
        $this->assertEquals($expected, $result);

        $migrate = $this->migrations->markMigrated(20150416223600, ['source' => 'Migrations']);
        $this->assertTrue($migrate);
        $result = $this->migrations->status(['source' => 'Migrations']);
        $expected[0]['status'] = 'up';
        $this->assertEquals($expected, $result);
    }

    /**
     * Tests that calling the migrations methods while passing the ``date``
     * parameter works as expected
     *
     * @return void
     */
    public function testMigrateDateOption()
    {
        // If we want to migrate to a date before the first first migration date,
        // we should not migrate anything
        $this->migrations->migrate(['date' => '20140705']);
        $expectedStatus = [
            [
                'status' => 'down',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable'
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable'
            ]
        ];
        $this->assertEquals($expectedStatus, $this->migrations->status());

        // If we want to migrate to a date between two migrations date,
        // we should migrate only the migrations BEFORE the date
        $this->migrations->migrate(['date' => '20150705']);
        $expectedStatus = [
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable'
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable'
            ]
        ];
        $this->assertEquals($expectedStatus, $this->migrations->status());
        $this->migrations->rollback();

        // If we want to migrate to a date after the last migration date,
        // we should migrate everything
        $this->migrations->migrate(['date' => '20150730']);
        $expectedStatus = [
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable'
            ],
            [
                'status' => 'up',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable'
            ]
        ];
        $this->assertEquals($expectedStatus, $this->migrations->status());

        // If we want to rollback to a date between two migrations date,
        // only migrations file having a date AFTER the date should be rollbacked
        $this->migrations->rollback(['date' => '20150705']);
        $expectedStatus = [
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable'
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable'
            ]
        ];
        $this->assertEquals($expectedStatus, $this->migrations->status());

        // If we want to rollback to a date prior to the first migration date,
        // everything should be rollbacked
        $this->migrations->migrate();
        $this->migrations->rollback([
            'date' => '20150703'
        ]);
        $expectedStatus = [
            [
                'status' => 'down',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable'
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable'
            ]
        ];
        $this->assertEquals($expectedStatus, $this->migrations->status());
    }

    /**
     * Tests migrating the baked snapshots
     *
     * @dataProvider migrationsProvider
     * @return void
     */
    public function testMigrateSnapshots($basePath)
    {
        $destination = ROOT . 'config' . DS . 'SnapshotTests' . DS;
        $timestamp = Util::getCurrentTimestamp();

        if (!file_exists($destination)) {
            mkdir($destination);
        }

        copy(
            $basePath . 'testCompositeConstraintsSnapshot.php',
            $destination . $timestamp . '_testCompositeConstraintsSnapshot.php'
        );

        $result = $this->migrations->migrate(['source' => 'SnapshotTests']);
        $this->assertTrue($result);

        $this->migrations->rollback(['source' => 'SnapshotTests']);

        unlink($destination . $timestamp . '_testCompositeConstraintsSnapshot.php');

        copy(
            $basePath . 'testNotEmptySnapshot.php',
            $destination . $timestamp . '_testNotEmptySnapshot.php'
        );

        $result = $this->migrations->migrate(['source' => 'SnapshotTests']);
        $this->assertTrue($result);

        $this->migrations->rollback(['source' => 'SnapshotTests']);

        unlink($destination . $timestamp . '_testNotEmptySnapshot.php');
    }

    /**
     * provides the path to the baked migrations
     *
     * @return array
     */
    public function migrationsProvider()
    {
        return [
            [Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS],
            [Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS . 'sqlite' . DS],
            [Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS . 'pgsql' . DS]
        ];
    }
}
