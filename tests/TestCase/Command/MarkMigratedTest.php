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

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\MigrationsDispatcher;
use Phinx\Migration\Manager\Environment;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;

/**
 * MarkMigratedTest class
 */
class MarkMigratedTest extends TestCase
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
     * setup method
     *
     * @return void
     */
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

    /**
     * tearDown method
     *
     * @return void
     */
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
        $commandTester->execute([
            'command' => $this->command->getName(),
            'version' => '2000000',
            '--connection' => 'test'
        ]);

        $this->assertContains(
            'A migration file matching version number `2000000` could not be found',
            $commandTester->getDisplay()
        );
        $result = $this->Connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetch('assoc');
        $this->assertFalse($result);
    }

    /**
     * Test executing "mark_migration"
     *
     * @return void
     */
    public function testExecute()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
            'version' => '20150416223600',
            '--connection' => 'test'
        ]);

        $this->assertContains('Migration successfully marked migrated !', $commandTester->getDisplay());

        $result = $this->Connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetch('assoc');
        $this->assertEquals('20150416223600', $result['version']);

        $commandTester->execute([
            'command' => $this->command->getName(),
            'version' => '20150416223600',
            '--connection' => 'test'
        ]);

        $this->assertContains(
            'The migration with version number `20150416223600` has already been marked as migrated.',
            $commandTester->getDisplay()
        );
        $result = $this->Connection->newQuery()->select(['*'])->from('phinxlog')->execute()->count();
        $this->assertEquals(1, $result);
    }
}
