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

use Cake\Datasource\ConnectionManager;
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
 * MarkMigratedTest class
 */
class StatusTest extends TestCase
{
    use DriverConnectionTrait;

    /**
     * Instance of a Symfony Command object
     *
     * @var \Phinx\Console\Command\AbstractCommand
     */
    protected $command;

    /**
     * Instance of a Cake Connection object
     *
     * @var \Cake\Database\Connection
     */
    protected $Connection;

    /**
     * Instance of a CommandTester object
     *
     * @var \Migrations\Test\CommandTester
     */
    protected $commandTester;

    /**
     * Instance of a StreamOutput object.
     * It will store the output from the CommandTester
     *
     * @var \Symfony\Component\Console\Output\StreamOutput
     */
    protected $streamOutput;

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

        $this->Connection = ConnectionManager::get('test');
        $this->Connection->getDriver()->connect();
        $this->pdo = $this->getDriverConnection($this->Connection->getDriver());

        $this->Connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->Connection->execute('DROP TABLE IF EXISTS numbers');

        $application = new MigrationsDispatcher('testing');
        $this->command = $application->find('status');
        $this->streamOutput = new StreamOutput(fopen('php://memory', 'w', false));
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->Connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->Connection->execute('DROP TABLE IF EXISTS numbers');
    }

    /**
     * Test executing the "status" command
     *
     * @return void
     */
    public function testExecute()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ];
        $commandTester = $this->getCommandTester($params);
        $commandTester->execute(['command' => $this->command->getName()] + $params);

        $display = $this->getDisplayFromOutput();
        $this->assertTextContains('down  20150704160200  CreateNumbersTable', $display);
        $this->assertTextContains('down  20150724233100  UpdateNumbersTable', $display);
        $this->assertTextContains('down  20150826191400  CreateLettersTable', $display);
    }

    /**
     * Test executing the "status" command with the JSON option
     *
     * @return void
     */
    public function testExecuteJson()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
            '--format' => 'json',
        ];
        $commandTester = $this->getCommandTester($params);
        $commandTester->execute(['command' => $this->command->getName()] + $params);
        $display = $this->getDisplayFromOutput();

        $expected = '[{"status":"down","id":20150704160200,"name":"CreateNumbersTable"},{"status":"down","id":20150724233100,"name":"UpdateNumbersTable"},{"status":"down","id":20150826191400,"name":"CreateLettersTable"},{"status":"down","id":20230628181900,"name":"CreateStoresTable"}]';

        $this->assertTextContains($expected, $display);
    }

    /**
     * Test executing the "status" command with the migrated migrations
     *
     * @return void
     */
    public function testExecuteWithMigrated()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ];
        $this->getCommandTester($params);
        $migrations = $this->getMigrations();
        $migrations->migrate();

        $params = [
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ];
        $commandTester = $this->getCommandTester($params);
        $commandTester->execute(['command' => $this->command->getName()] + $params);

        $display = $this->getDisplayFromOutput();
        $this->assertTextContains('up  20150704160200  CreateNumbersTable', $display);
        $this->assertTextContains('up  20150724233100  UpdateNumbersTable', $display);
        $this->assertTextContains('up  20150826191400  CreateLettersTable', $display);

        $migrations->rollback(['target' => 'all']);
    }

    /**
     * Test executing the "status" command with inconsistency in the migrations files
     *
     * @return void
     */
    public function testExecuteWithInconsistency()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ];
        $this->getCommandTester($params);
        $migrations = $this->getMigrations();
        $migrations->migrate();

        $migrationPaths = $migrations->getConfig()->getMigrationPaths();
        $migrationPath = array_pop($migrationPaths);
        $origin = $migrationPath . DS . '20150724233100_update_numbers_table.php';
        $destination = $migrationPath . DS . '_20150724233100_update_numbers_table.php';
        rename($origin, $destination);

        $params = [
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ];
        $commandTester = $this->getCommandTester($params);
        $commandTester->execute(['command' => $this->command->getName()] + $params);

        $display = $this->getDisplayFromOutput();
        $this->assertTextContains('up  20150704160200  CreateNumbersTable', $display);
        $this->assertTextContains('up  20150724233100  UpdateNumbersTable  ** MISSING **', $display);
        $this->assertTextContains('up  20150826191400  CreateLettersTable', $display);

        rename($destination, $origin);

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
