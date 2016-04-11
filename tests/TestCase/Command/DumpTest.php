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
namespace Migrations\Test\Command;

use Cake\Core\Plugin;
use Cake\Database\Schema\Collection;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use Migrations\CakeManager;
use Migrations\Migrations;
use Migrations\MigrationsDispatcher;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * DumpTest class
 */
class DumpTest extends TestCase
{

    use StringCompareTrait;

    /**
     * Instance of a Symfony Command object
     *
     * @var \Symfony\Component\Console\Command\Command
     */
    protected $command;

    /**
     * Instance of a Phinx Config object
     *
     * @var \Phinx\Config\Config
     */
    protected $config = [];

    /**
     * Instance of a Cake Connection object
     *
     * @var \Cake\Database\Connection
     */
    protected $Connection;

    /**
     * Instance of a StreamOutput object.
     * It will store the output from the CommandTester
     *
     * @var \Symfony\Component\Console\Output\StreamOutput
     */
    protected $streamOutput;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->Connection = ConnectionManager::get('test');
        $application = new MigrationsDispatcher('testing');
        $this->command = $application->find('dump');
        $this->streamOutput = new StreamOutput(fopen('php://memory', 'w', false));
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Migration' . DS;
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        unset($this->Connection, $this->command, $this->streamOutput);
    }

    /**
     * Test executing "dump" with no tables in the database
     *
     * @return void
     */
    public function testExecuteNoTables()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'TestsMigrations'
        ];
        $commandTester = $this->getCommandTester($params);

        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--source' => 'TestsMigrations'
        ]);

        $display = $commandTester->getDisplay();
        $this->assertTextContains('No tables were found : the dump file was not created', $display);
    }

    /**
     * Test executing "dump" with no tables in the database
     *
     * @return void
     */
    public function testExecuteTables()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'TestsMigrations'
        ];
        $commandTester = $this->getCommandTester($params);
        $migrations = $this->getMigrations();
        $migrations->migrate();

        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--source' => 'TestsMigrations'
        ]);

        $dumpFilePath = ROOT . 'config' . DS . 'TestsMigrations' . DS . 'schema-dump-test';
        $this->assertTrue(file_exists($dumpFilePath));

        $generatedDump = unserialize(file_get_contents($dumpFilePath));

        $this->assertCount(2, $generatedDump);
        $this->assertArrayHasKey('letters', $generatedDump);
        $this->assertArrayHasKey('numbers', $generatedDump);
        $this->assertInstanceOf('Cake\Database\Schema\Table', $generatedDump['numbers']);
        $this->assertInstanceOf('Cake\Database\Schema\Table', $generatedDump['letters']);
        $this->assertEquals(['id', 'number', 'radix'], $generatedDump['numbers']->columns());
        $this->assertEquals(['id', 'letter'], $generatedDump['letters']->columns());

        $migrations->rollback(['target' => 0]);
    }

    /**
     * Gets a pre-configured of a CommandTester object that is initialized for each
     * test methods. This is needed in order to define the same PDO connection resource
     * between every objects needed during the tests.
     * This is mandatory for the SQLite database vendor, so phinx objects interacting
     * with the database have the same connection resource as CakePHP objects.
     *
     * @return \Symfony\Component\Console\Tester\CommandTester
     */
    protected function getCommandTester($params)
    {
        if (!$this->Connection->driver()->isConnected()) {
            $this->Connection->driver()->connect();
        }

        $input = new ArrayInput($params, $this->command->getDefinition());
        $this->command->setInput($input);
        $manager = new CakeManager($this->command->getConfig(), $this->streamOutput);
        $manager->getEnvironment('default')->getAdapter()->setConnection($this->Connection->driver()->connection());
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
            'source' => 'TestsMigrations'
        ];
        $migrations = new Migrations($params);
        $migrations
            ->getManager($this->command->getConfig())
            ->getEnvironment('default')
            ->getAdapter()
            ->setConnection($this->Connection->driver()->connection());

        $tables = (new Collection($this->Connection))->listTables();
        if (in_array('phinxlog', $tables)) {
            $ormTable = TableRegistry::get('phinxlog', ['connection' => $this->Connection]);
            $query = $this->Connection->driver()->schemaDialect()->truncateTableSql($ormTable->schema());
            foreach ($query as $stmt) {
                $this->Connection->execute($stmt);
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
