<?php

namespace Migrations\Test\Command;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\MigrationsDispatcher;
use Phinx\Migration\Manager\Environment;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Console\Command\Status;

class MarkMigratedTest extends TestCase
{
    protected $Connection;

    protected $config = [];

    protected $command;

    public function setUp()
    {
        parent::setUp();

        $this->Connection = ConnectionManager::get('test');
        $connectionConfig = $this->Connection->config();
        $this->Connection->execute('DROP TABLE IF EXISTS phinxlog');

        $this->config = new Config([
            'paths' => [
                'migrations' => __FILE__,
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_database' => 'cakephp_test',
                'default' => [
                    'adapter' => getenv('DB'),
                    'host' => '127.0.0.1',
                    'name' => !empty($connectionConfig['database']) ? $connectionConfig['database'] : '',
                    'user' => !empty($connectionConfig['username']) ? $connectionConfig['username'] : '',
                    'pass' => !empty($connectionConfig['password']) ? $connectionConfig['password'] : ''
                ]
            ]
        ]);

        $application = new MigrationsDispatcher('testing');
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $this->command = $application->find('mark_migrated');

        $Environment = new Environment('default', $this->config['environments']['default']);

        $Manager = $this->getMock('\Phinx\Migration\Manager', [], [$this->config, $output]);
        $Manager->expects($this->any())
            ->method('getEnvironment')
            ->will($this->returnValue($Environment));

        $this->command->setManager($Manager);
    }

    public function tearDown()
    {
        parent::tearDown();
        unset($this->Connection, $this->config, $this->command);
    }

    /**
     * Test executing "mark_migration" with no file matching the version number
     *
     * @return void
     */
    public function testExecuteNoFile()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['command' => $this->command->getName(), 'version' => '2000000', '--connection' => 'test']);

        $this->assertContains('A migration file matching version number `2000000` could not be found', $commandTester->getDisplay());
        $result = $this->Connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetch('assoc');
        $this->assertFalse($result);
    }

    /**
     * Test executing "mark_migration" with no file matching the version number
     *
     * @return void
     */
    public function testExecute()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['command' => $this->command->getName(), 'version' => '20150416223600', '--connection' => 'test']);

        $this->assertContains('Migration successfully marked migrated !', $commandTester->getDisplay());

        $result = $this->Connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetch('assoc');
        $this->assertEquals('20150416223600', $result['version']);
    }
}
