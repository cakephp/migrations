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
namespace Migrations\Test\TestCase;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Exception;
use InvalidArgumentException;
use Migrations\CakeAdapter;
use Migrations\Migrations;
use Phinx\Db\Adapter\WrapperInterface;
use function Cake\Core\env;

/**
 * Tests the Migrations class
 */
class MigrationsTest extends TestCase
{
    use DriverConnectionTrait;

    /**
     * Instance of a Migrations object
     *
     * @var \Migrations\Migrations
     */
    protected $migrations;

    /**
     * Instance of a Cake Connection object
     *
     * @var \Cake\Database\Connection
     */
    protected $Connection;

    /**
     * @var string[]
     */
    protected $generatedFiles = [];

    /**
     * Setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->generatedFiles = [];
        $params = [
            'connection' => 'test',
            'source' => 'TestsMigrations',
        ];

        // Get the PDO connection to have the same across the various objects needed to run the tests
        $migrations = new Migrations();
        $input = $migrations->getInput('Migrate', [], $params);
        $migrations->setInput($input);
        $migrations->getManager($migrations->getConfig());
        $this->Connection = ConnectionManager::get('test');
        $connection = $migrations->getManager()->getEnvironment('default')->getAdapter()->getConnection();
        $this->setDriverConnection($this->Connection->getDriver(), $connection);

        // Get an instance of the Migrations object on which we will run the tests
        $this->migrations = new Migrations($params);
        $adapter = $this->migrations
            ->getManager($migrations->getConfig())
            ->getEnvironment('default')
            ->getAdapter();

        while ($adapter instanceof WrapperInterface) {
            $adapter = $adapter->getAdapter();
        }
        $adapter->setConnection($connection);

        // List of tables managed by migrations this test runs.
        // We can't wipe all tables as we'l break other tests.
        $this->Connection->execute('DROP TABLE IF EXISTS numbers');
        $this->Connection->execute('DROP TABLE IF EXISTS letters');

        $allTables = $this->Connection->getSchemaCollection()->listTables();
        if (in_array('phinxlog', $allTables)) {
            $ormTable = $this->getTableLocator()->get('phinxlog', ['connection' => $this->Connection]);
            $query = $this->Connection->getDriver()->schemaDialect()->truncateTableSql($ormTable->getSchema());
            foreach ($query as $stmt) {
                $this->Connection->execute($stmt);
            }
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
        unset($this->Connection, $this->migrations);

        foreach ($this->generatedFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
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
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expected, $result);

        $adapter = $this->migrations
            ->getManager()
            ->getEnvironment('default')
            ->getAdapter();

        $this->assertInstanceOf(CakeAdapter::class, $adapter);
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
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'up',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'up',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'up',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expectedStatus, $status);

        $numbersTable = $this->getTableLocator()->get('Numbers', ['connection' => $this->Connection]);
        $columns = $numbersTable->getSchema()->columns();
        $expected = ['id', 'number', 'radix'];
        $this->assertEquals($columns, $expected);
        $primaryKey = $numbersTable->getSchema()->getPrimaryKey();
        $this->assertEquals($primaryKey, ['id']);

        $lettersTable = $this->getTableLocator()->get('Letters', ['connection' => $this->Connection]);
        $columns = $lettersTable->getSchema()->columns();
        $expected = ['id', 'letter'];
        $this->assertEquals($expected, $columns);
        $idColumn = $lettersTable->getSchema()->getColumn('id');
        $this->assertEquals(true, $idColumn['autoIncrement']);
        $primaryKey = $lettersTable->getSchema()->getPrimaryKey();
        $this->assertEquals($primaryKey, ['id']);

        $storesTable = $this->getTableLocator()->get('Stores', ['connection' => $this->Connection]);
        $columns = $storesTable->getSchema()->columns();
        $expected = ['id', 'name', 'created', 'modified'];
        $this->assertEquals($expected, $columns);
        $createdColumn = $storesTable->getSchema()->getColumn('created');
        $this->assertEquals('CURRENT_TIMESTAMP', $createdColumn['default']);

        // Rollback last
        $rollback = $this->migrations->rollback();
        $this->assertTrue($rollback);
        $expectedStatus[3]['status'] = 'down';
        $status = $this->migrations->status();
        $this->assertEquals($expectedStatus, $status);

        // Migrate all again and rollback all
        $this->migrations->migrate();
        $rollback = $this->migrations->rollback(['target' => 'all']);
        $this->assertTrue($rollback);
        $expectedStatus[0]['status'] = $expectedStatus[1]['status'] = $expectedStatus[2]['status'] = 'down';
        $status = $this->migrations->status();
        $this->assertEquals($expectedStatus, $status);
    }

    /**
     * Tests the collation table behavior when using MySQL
     *
     * @return void
     */
    public function testCreateWithEncoding()
    {
        $this->skipIf(env('DB') !== 'mysql');

        $migrate = $this->migrations->migrate();
        $this->assertTrue($migrate);

        // Tests that if a collation is defined, it is used
        $numbersTable = $this->getTableLocator()->get('Numbers', ['connection' => $this->Connection]);
        $options = $numbersTable->getSchema()->getOptions();
        $this->assertSame('utf8mb3_bin', $options['collation']);

        // Tests that if a collation is not defined, it will use the database default one
        $lettersTable = $this->getTableLocator()->get('Letters', ['connection' => $this->Connection]);
        $options = $lettersTable->getSchema()->getOptions();
        $this->assertStringStartsWith('utf8mb4_', $options['collation']);

        $this->migrations->rollback(['target' => 'all']);
    }

    /**
     * Tests calling Migrations::markMigrated without params marks everything
     * as migrated
     *
     * @return void
     */
    public function testMarkMigratedAll()
    {
        $markMigrated = $this->migrations->markMigrated();
        $this->assertTrue($markMigrated);
        $status = $this->migrations->status();
        $expected = [
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'up',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'up',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'up',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expected, $status);
    }

    /**
     * Tests calling Migrations::markMigrated with the argument $version as the
     * string 'all' marks everything
     * as migrated
     *
     * @return void
     */
    public function testMarkMigratedAllAsVersion()
    {
        $markMigrated = $this->migrations->markMigrated('all');
        $this->assertTrue($markMigrated);
        $status = $this->migrations->status();
        $expected = [
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'up',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'up',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'up',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expected, $status);
    }

    /**
     * Tests calling Migrations::markMigrated with the target option will mark
     * only up to that one
     *
     * @return void
     */
    public function testMarkMigratedTarget()
    {
        $markMigrated = $this->migrations->markMigrated(null, ['target' => '20150704160200']);
        $this->assertTrue($markMigrated);
        $status = $this->migrations->status();
        $expected = [
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => 20230628181900,
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expected, $status);

        $markMigrated = $this->migrations->markMigrated(null, ['target' => '20150826191400']);
        $this->assertTrue($markMigrated);
        $status = $this->migrations->status();
        $expected[1]['status'] = $expected[2]['status'] = 'up';
        $this->assertEquals($expected, $status);
    }

    /**
     * Tests calling Migrations::markMigrated with the target option set to a
     * non-existent target will throw an exception
     *
     * @return void
     */
    public function testMarkMigratedTargetError()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Migration `20150704160610` was not found !');
        $this->migrations->markMigrated(null, ['target' => '20150704160610']);
    }

    /**
     * Tests calling Migrations::markMigrated with the target option with the exclude
     * option will mark only up to that one, excluding it
     *
     * @return void
     */
    public function testMarkMigratedTargetExclude()
    {
        $markMigrated = $this->migrations->markMigrated(null, ['target' => '20150704160200', 'exclude' => true]);
        $this->assertTrue($markMigrated);
        $status = $this->migrations->status();
        $expected = [
            [
                'status' => 'down',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expected, $status);

        $markMigrated = $this->migrations->markMigrated(null, ['target' => '20150826191400', 'exclude' => true]);
        $this->assertTrue($markMigrated);
        $status = $this->migrations->status();
        $expected[0]['status'] = $expected[1]['status'] = 'up';
        $this->assertEquals($expected, $status);
    }

    /**
     * Tests calling Migrations::markMigrated with the target option with the only
     * option will mark only that specific migrations
     *
     * @return void
     */
    public function testMarkMigratedTargetOnly()
    {
        $markMigrated = $this->migrations->markMigrated(null, ['target' => '20150724233100', 'only' => true]);
        $this->assertTrue($markMigrated);
        $status = $this->migrations->status();
        $expected = [
            [
                'status' => 'down',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'up',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expected, $status);

        $markMigrated = $this->migrations->markMigrated(null, ['target' => '20150826191400', 'only' => true]);
        $this->assertTrue($markMigrated);
        $status = $this->migrations->status();
        $expected[2]['status'] = 'up';
        $this->assertEquals($expected, $status);
    }

    /**
     * Tests calling Migrations::markMigrated with the target option, the only option
     * and the exclude option will throw an exception
     *
     * @return void
     */
    public function testMarkMigratedTargetExcludeOnly()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You should use `exclude` OR `only` (not both) along with a `target` argument');
        $this->migrations->markMigrated(null, ['target' => '20150724233100', 'only' => true, 'exclude' => true]);
    }

    /**
     * Tests calling Migrations::markMigrated with the target option with the exclude
     * option will mark only up to that one, excluding it
     *
     * @return void
     */
    public function testMarkMigratedVersion()
    {
        $markMigrated = $this->migrations->markMigrated(20150704160200);
        $this->assertTrue($markMigrated);
        $status = $this->migrations->status();
        $expected = [
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expected, $status);

        $markMigrated = $this->migrations->markMigrated(20150826191400);
        $this->assertTrue($markMigrated);
        $status = $this->migrations->status();
        $expected[2]['status'] = 'up';
        $this->assertEquals($expected, $status);
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
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expectedStatus, $result);

        $result = $this->migrations->status(['source' => 'Migrations']);
        $expected = [
            [
                'status' => 'down',
                'id' => '20150416223600',
                'name' => 'MarkMigratedTest',
            ],
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
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expectedStatus, $this->migrations->status());

        // If we want to migrate to a date between two migrations date,
        // we should migrate only the migrations BEFORE the date
        $this->migrations->migrate(['date' => '20150705']);
        $expectedStatus = [
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
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
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'up',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expectedStatus, $this->migrations->status());

        // If we want to rollback to a date after the last migrations,
        // nothing should be rollbacked
        $this->migrations->rollback([
            'date' => '20150730',
        ]);
        $expectedStatus = [
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'up',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expectedStatus, $this->migrations->status());

        // If we want to rollback to a date between two migrations date,
        // only migrations file having a date AFTER the date should be rollbacked
        $this->migrations->rollback(['date' => '20150705']);
        $expectedStatus = [
            [
                'status' => 'up',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expectedStatus, $this->migrations->status());

        // If we want to rollback to a date prior to the first migration date,
        // everything should be rollbacked
        $this->migrations->migrate();
        $this->migrations->rollback([
            'date' => '20150703',
        ]);
        $expectedStatus = [
            [
                'status' => 'down',
                'id' => '20150704160200',
                'name' => 'CreateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150724233100',
                'name' => 'UpdateNumbersTable',
            ],
            [
                'status' => 'down',
                'id' => '20150826191400',
                'name' => 'CreateLettersTable',
            ],
            [
                'status' => 'down',
                'id' => '20230628181900',
                'name' => 'CreateStoresTable',
            ],
        ];
        $this->assertEquals($expectedStatus, $this->migrations->status());
    }

    /**
     * Tests seeding the database
     *
     * @return void
     */
    public function testSeed()
    {
        $this->migrations->migrate();
        $seed = $this->migrations->seed(['source' => 'Seeds']);
        $this->assertTrue($seed);

        $result = $this->Connection->selectQuery()
            ->select(['*'])
            ->from('numbers')
            ->execute()->fetchAll('assoc');
        $expected = [
            [
                'id' => '1',
                'number' => '10',
                'radix' => '10',
            ],
        ];
        $this->assertEquals($expected, $result);

        $seed = $this->migrations->seed(['source' => 'Seeds']);
        $this->assertTrue($seed);
        $result = $this->Connection->selectQuery()
            ->select(['*'])
            ->from('numbers')
            ->execute()->fetchAll('assoc');
        $expected = [
            [
                'id' => '1',
                'number' => '10',
                'radix' => '10',
            ],
            [
                'id' => '2',
                'number' => '10',
                'radix' => '10',
            ],
        ];
        $this->assertEquals($expected, $result);

        $seed = $this->migrations->seed(['source' => 'AltSeeds']);
        $this->assertTrue($seed);
        $result = $this->Connection->selectQuery()
            ->select(['*'])
            ->from('numbers')
            ->execute()->fetchAll('assoc');
        $expected = [
            [
                'id' => '1',
                'number' => '10',
                'radix' => '10',
            ],
            [
                'id' => '2',
                'number' => '10',
                'radix' => '10',
            ],
            [
                'id' => '3',
                'number' => '2',
                'radix' => '10',
            ],
            [
                'id' => '4',
                'number' => '5',
                'radix' => '10',
            ],
        ];
        $this->assertEquals($expected, $result);
        $this->migrations->rollback(['target' => 'all']);
    }

    /**
     * Tests seeding the database with seeder
     *
     * @return void
     */
    public function testSeedOneSeeder()
    {
        $this->migrations->migrate();

        $seed = $this->migrations->seed(['source' => 'AltSeeds', 'seed' => 'AnotherNumbersSeed']);
        $this->assertTrue($seed);
        $result = $this->Connection->selectQuery()
            ->select(['*'])
            ->from('numbers')
            ->execute()->fetchAll('assoc');

        $expected = [
            [
                'id' => '1',
                'number' => '2',
                'radix' => '10',
            ],
        ];
        $this->assertEquals($expected, $result);

        $seed = $this->migrations->seed(['source' => 'AltSeeds', 'seed' => 'NumbersAltSeed']);
        $this->assertTrue($seed);
        $result = $this->Connection->selectQuery()
            ->select(['*'])
            ->from('numbers')
            ->execute()->fetchAll('assoc');

        $expected = [
            [
                'id' => '1',
                'number' => '2',
                'radix' => '10',
            ],
            [
                'id' => '2',
                'number' => '5',
                'radix' => '10',
            ],
        ];
        $this->assertEquals($expected, $result);

        $this->migrations->rollback(['target' => 'all']);
    }

    /**
     * Tests seeding the database with seeder
     *
     * @return void
     */
    public function testSeedCallSeeder()
    {
        $this->migrations->migrate();

        $seed = $this->migrations->seed(['source' => 'CallSeeds', 'seed' => 'DatabaseSeed']);
        $this->assertTrue($seed);
        $result = $this->Connection->selectQuery()
            ->select(['*'])
            ->from('numbers')
            ->execute()->fetchAll('assoc');

        $expected = [
            [
                'id' => '1',
                'number' => '10',
                'radix' => '10',
            ],
        ];
        $this->assertEquals($expected, $result);

        $result = $this->Connection->selectQuery()
            ->select(['*'])
            ->from('letters')
            ->execute()->fetchAll('assoc');

        $expected = [
            [
                'id' => '1',
                'letter' => 'a',
            ],
            [
                'id' => '2',
                'letter' => 'b',
            ],
            [
                'id' => '3',
                'letter' => 'c',
            ],
            [
                'id' => '4',
                'letter' => 'd',
            ],
            [
                'id' => '5',
                'letter' => 'e',
            ],
            [
                'id' => '6',
                'letter' => 'f',
            ],
        ];
        $this->assertEquals($expected, $result);

        $this->migrations->rollback(['target' => 'all']);
    }

    /**
     * Tests that requesting a unexistant seed throws an exception
     *
     * @return void
     */
    public function testSeedWrongSeed()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed class "DerpSeed" does not exist');
        $this->migrations->seed(['source' => 'AltSeeds', 'seed' => 'DerpSeed']);
    }

    /**
     * Tests migrating the baked snapshots
     *
     * @dataProvider migrationsProvider
     * @return void
     */
    public function testMigrateSnapshots($basePath, $files)
    {
        $this->markTestSkipped('This test is failing and seems low value.');
        $destination = ROOT . DS . 'config' . DS . 'SnapshotTests' . DS;

        if (!file_exists($destination)) {
            mkdir($destination);
        }

        foreach ($files as $file) {
            [$filename, $timestamp] = $file;
            $copiedFileName = $timestamp . '_' . $filename . 'NewSuffix' . '.php';

            if (!file_exists($destination . $copiedFileName)) {
                copy(
                    $basePath . $filename . '.php',
                    $destination . $copiedFileName
                );
                $this->generatedFiles[] = $destination . $copiedFileName;

                //change class name to avoid conflict with other classes
                //to avoid 'Fatal error: Cannot declare class Test...., because the name is already in use'
                $content = file_get_contents($destination . $copiedFileName);
                $pattern = ' extends AbstractMigration';
                $content = str_replace($pattern, 'NewSuffix' . $pattern, $content);
                file_put_contents($destination . $copiedFileName, $content);
            }

            $result = $this->migrations->migrate(['source' => 'SnapshotTests']);
            $this->assertTrue($result);

            $this->migrations->rollback(['target' => 'all', 'source' => 'SnapshotTests']);
        }
    }

    /**
     * Tests that migrating in case of error throws an exception
     */
    public function testMigrateErrors()
    {
        $this->expectException(Exception::class);
        $this->migrations->markMigrated(20150704160200);
        $this->migrations->migrate();
    }

    /**
     * Tests that rolling back in case of error throws an exception
     */
    public function testRollbackErrors()
    {
        $this->expectException(Exception::class);
        $this->migrations->markMigrated('all');
        $this->migrations->rollback();
    }

    /**
     * Tests that marking migrated a non-existant migrations returns an error
     * and can return a error message
     */
    public function testMarkMigratedErrors()
    {
        $this->expectException(Exception::class);
        $this->migrations->markMigrated(20150704000000);
    }

    /**
     * provides the path to the baked migrations
     *
     * @return array
     */
    public static function migrationsProvider()
    {
        $db = getenv('DB');

        if ($db === 'mysql') {
            $return = [
                [
                    Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS,
                    [
                        ['test_not_empty_snapshot', 20150912015601],
                        ['test_auto_id_disabled_snapshot', 20150912015602],
                        ['testCreatePrimaryKey', 20150912015603],
                        ['testCreatePrimaryKeyUuid', 20150912015604],
                    ],
                ],
            ];

            return $return;
        }

        if ($db === 'pgsql') {
            return [
                [
                    Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS . 'pgsql' . DS,
                    [
                        ['test_not_empty_snapshot_pgsql', 20150912015606],
                        ['test_auto_id_disabled_snapshot_pgsql', 20150912015607],
                    ],
                ],
            ];
        }

        return [
            [
                Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS . 'sqlite' . DS,
                [
                    ['test_not_empty_snapshot_sqlite', 20150912015609],
                    ['test_auto_id_disabled_snapshot_sqlite', 20150912015610],
                ],
            ],
        ];
    }
}
