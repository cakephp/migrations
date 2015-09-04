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
use Symfony\Component\Console\Tester\CommandTester;

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
     * Instance of a CommandTester object
     *
     * @var \Symfony\Component\Console\Tester\CommandTester
     */
    protected $commandTester;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->Connection = ConnectionManager::get('test');
        $this->Connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->Connection->execute('DROP TABLE IF EXISTS numbers');

        $application = new MigrationsDispatcher('testing');
        $this->command = $application->find('mark_migrated');
        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        $this->Connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->Connection->execute('DROP TABLE IF EXISTS numbers');
        unset($this->Connection, $this->commandTester, $this->command);
    }

    /**
     * Test executing "mark_migration" with no file matching the version number
     *
     * @return void
     */
    public function testExecuteNoFile()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'version' => '2000000',
            '--connection' => 'test'
        ]);

        $this->assertContains(
            'A migration file matching version number `2000000` could not be found',
            $this->commandTester->getDisplay()
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
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'version' => '20150416223600',
            '--connection' => 'test'
        ]);

        $this->assertContains('Migration successfully marked migrated !', $this->commandTester->getDisplay());

        $result = $this->Connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetch('assoc');
        $this->assertEquals('20150416223600', $result['version']);

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'version' => '20150416223600',
            '--connection' => 'test'
        ]);

        $this->assertContains(
            'The migration with version number `20150416223600` has already been marked as migrated.',
            $this->commandTester->getDisplay()
        );
        $result = $this->Connection->newQuery()->select(['*'])->from('phinxlog')->execute()->count();
        $this->assertEquals(1, $result);
    }
}
