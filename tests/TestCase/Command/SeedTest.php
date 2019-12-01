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

use Cake\Database\Schema\Collection;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Migrations\CakeManager;
use Migrations\Migrations;
use Migrations\MigrationsDispatcher;
use Phinx\Db\Adapter\WrapperInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * SeedTest class
 */
class SeedTest extends TestCase
{

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
        $this->Connection->connect();
        $this->pdo = $this->Connection->getDriver()->getConnection();
        $application = new MigrationsDispatcher('testing');
        $this->command = $application->find('seed');
        $this->streamOutput = new StreamOutput(fopen('php://memory', 'w', false));
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        $this->Connection->getDriver()->setConnection($this->pdo);
        $this->Connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->Connection->execute('DROP TABLE IF EXISTS numbers');
        unset($this->Connection, $this->command, $this->streamOutput);
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

        $result = $this->Connection->newQuery()
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

        $result = $this->Connection->newQuery()
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
     * @return \Symfony\Component\Console\Tester\CommandTester
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
        $commandTester = new \Migrations\Test\CommandTester($this->command);

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
