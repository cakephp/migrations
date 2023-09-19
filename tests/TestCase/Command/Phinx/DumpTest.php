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
namespace Migrations\Test\TestCase\Command\Phinx;

use Cake\Core\Plugin;
use Cake\Database\Schema\Collection;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use Migrations\CakeManager;
use Migrations\Migrations;
use Migrations\MigrationsDispatcher;
use Migrations\Test\CommandTester;
use Migrations\Test\TestCase\DriverConnectionTrait;
use PDO;
use Phinx\Db\Adapter\WrapperInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * DumpTest class
 */
class DumpTest extends TestCase
{
    use DriverConnectionTrait;
    use StringCompareTrait;

    /**
     * Instance of a Symfony Command object
     *
     * @var \Symfony\Component\Console\Command\Command
     */
    protected $command;

    /**
     * Instance of a Cake Connection object
     *
     * @var \Cake\Database\Connection
     */
    protected $connection;

    /**
     * Instance of a StreamOutput object.
     * It will store the output from the CommandTester
     *
     * @var \Symfony\Component\Console\Output\StreamOutput
     */
    protected $streamOutput;

    /**
     * @var string
     */
    protected $dumpfile = '';

    /**
     * @var \PDO|null
     */
    protected ?PDO $pdo = null;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->connection = ConnectionManager::get('test');
        $this->connection->getDriver()->connect();
        $this->pdo = $this->getDriverConnection($this->connection->getDriver());

        $application = new MigrationsDispatcher('testing');
        $this->command = $application->find('dump');
        $this->streamOutput = new StreamOutput(fopen('php://memory', 'w', false));
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS;

        $this->connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->connection->execute('DROP TABLE IF EXISTS numbers');
        $this->connection->execute('DROP TABLE IF EXISTS letters');
        $this->connection->execute('DROP TABLE IF EXISTS parts');
        $this->dumpfile = ROOT . DS . 'config/TestsMigrations/schema-dump-test.lock';
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->connection->execute('DROP TABLE IF EXISTS numbers');
        $this->connection->execute('DROP TABLE IF EXISTS letters');
        $this->connection->execute('DROP TABLE IF EXISTS parts');
    }

    /**
     * Test executing "dump" with tables in the database
     *
     * @return void
     */
    public function testExecuteTables()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ];
        $commandTester = $this->getCommandTester($params);
        $migrations = $this->getMigrations();
        $migrations->migrate();

        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertFileExists($this->dumpfile);
        $generatedDump = unserialize(file_get_contents($this->dumpfile));

        $this->assertArrayHasKey('letters', $generatedDump);
        $this->assertArrayHasKey('numbers', $generatedDump);
        $this->assertInstanceOf(TableSchema::class, $generatedDump['numbers']);
        $this->assertInstanceOf(TableSchema::class, $generatedDump['letters']);
        $this->assertEquals(['id', 'number', 'radix'], $generatedDump['numbers']->columns());
        $this->assertEquals(['id', 'letter'], $generatedDump['letters']->columns());

        $migrations->rollback(['target' => 'all']);
    }

    /**
     * Gets a pre-configured of a CommandTester object that is initialized for each
     * test methods. This is needed in order to define the same PDO connection resource
     * between every objects needed during the tests.
     * This is mandatory for the SQLite database vendor, so phinx objects interacting
     * with the database have the same connection resource as CakePHP objects.
     *
     * @param array $params
     * @return \Migrations\Test\CommandTester
     */
    protected function getCommandTester($params)
    {
        $input = new ArrayInput($params, $this->command->getDefinition());
        $this->command->setInput($input);
        $manager = new CakeManager($this->command->getConfig(), $input, $this->streamOutput);

        $adapter = $manager
            ->getEnvironment('default')
            ->getAdapter();
        while ($adapter instanceof WrapperInterface) {
            $adapter = $adapter->getAdapter();
        }
        $adapter->setConnection($this->pdo);

        $this->command->setManager($manager);
        $commandTester = new CommandTester($this->command);

        return $commandTester;
    }

    /**
     * Gets a Migrations object in order to easily create and drop tables during the
     * tests
     *
     * @return \Migrations\Migrations
     */
    protected function getMigrations()
    {
        $params = [
            'connection' => 'test',
            'source' => 'TestsMigrations',
        ];
        $migrations = new Migrations($params);
        $adapter = $migrations
            ->getManager($this->command->getConfig())
            ->getEnvironment('default')
            ->getAdapter();

        while ($adapter instanceof WrapperInterface) {
            $adapter = $adapter->getAdapter();
        }

        $adapter->setConnection($this->pdo);

        $tables = (new Collection($this->connection))->listTables();
        if (in_array('phinxlog', $tables)) {
            $ormTable = $this->getTableLocator()->get('phinxlog', ['connection' => $this->connection]);
            $query = $this->connection->getDriver()->schemaDialect()->truncateTableSql($ormTable->getSchema());
            foreach ($query as $stmt) {
                $this->connection->execute($stmt);
            }
        }

        return $migrations;
    }

    /**
     * Extract the content that was stored in self::$streamOutput.
     *
     * @return string
     */
    protected function getDisplayFromOutput()
    {
        rewind($this->streamOutput->getStream());
        $display = stream_get_contents($this->streamOutput->getStream());

        return str_replace(PHP_EOL, "\n", $display);
    }
}
