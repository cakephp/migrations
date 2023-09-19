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
 * SeedTest class
 */
class SeedTest extends TestCase
{
    use DriverConnectionTrait;

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
        $this->command = $application->find('seed');
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
        $this->connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->connection->execute('DROP TABLE IF EXISTS numbers');
    }

    /**
     * Test executing the "seed" command in a standard way
     *
     * @return void
     */
    public function testExecute()
    {
        $params = [
            '--connection' => 'test',
        ];
        $commandTester = $this->getCommandTester($params);
        $migrations = $this->getMigrations();
        $migrations->migrate();

        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--seed' => 'NumbersSeed',
        ]);

        $display = $this->getDisplayFromOutput();
        $this->assertTextContains('== NumbersSeed: seeded', $display);

        $result = $this->connection->selectQuery()
            ->select(['*'])
            ->from('numbers')
            ->order('id DESC')
            ->limit(1)
            ->execute()->fetchAll('assoc');
        $expected = [
            [
                'id' => '1',
                'number' => '10',
                'radix' => '10',
            ],
        ];
        $this->assertEquals($expected, $result);

        $migrations->rollback(['target' => 'all']);
    }

    /**
     * Test executing the "seed" command with custom params
     *
     * @return void
     */
    public function testExecuteCustomParams()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'AltSeeds',
        ];
        $commandTester = $this->getCommandTester($params);
        $migrations = $this->getMigrations();
        $migrations->migrate();

        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--source' => 'AltSeeds',
        ]);

        $display = $this->getDisplayFromOutput();
        $this->assertTextContains('== NumbersAltSeed: seeded', $display);

        $result = $this->connection->selectQuery()
            ->select(['*'])
            ->from('numbers')
            ->order('id DESC')
            ->limit(1)
            ->execute()->fetchAll('assoc');
        $expected = [
            [
                'id' => '2',
                'number' => '5',
                'radix' => '10',
            ],
        ];
        $this->assertEquals($expected, $result);
        $migrations->rollback(['target' => 'all']);
    }

    /**
     * Test executing the "seed" command with wrong custom params (no seed found)
     *
     * @return void
     */
    public function testExecuteWrongCustomParams()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'DerpSeeds',
        ];
        $commandTester = $this->getCommandTester($params);
        $migrations = $this->getMigrations();
        $migrations->migrate();

        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--source' => 'DerpSeeds',
        ]);

        $display = $this->getDisplayFromOutput();
        $this->assertTextNotContains('seeded', $display);
        $migrations->rollback(['target' => 'all']);
    }

    /**
     * Test executing the "seed" command with seeders using the call method
     *
     * @return void
     */
    public function testExecuteSeedCallingOtherSeeders()
    {
        $params = [
            '--connection' => 'test',
            '--source' => 'CallSeeds',
        ];
        $commandTester = $this->getCommandTester($params);
        $migrations = $this->getMigrations();
        $migrations->migrate();

        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--source' => 'CallSeeds',
            '--seed' => 'DatabaseSeed',
        ]);

        $display = $this->getDisplayFromOutput();
        $this->assertTextContains('==== NumbersCallSeed: seeded', $display);
        $this->assertTextContains('==== LettersSeed: seeded', $display);
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
     * Extract the content that was stored in self::$output.
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
