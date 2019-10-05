<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\ConsoleIo;
use Cake\TestSuite\Stub\ConsoleOutput;
use Cake\TestSuite\TestCase;
use Migrations\MigrationsDispatcher;
use Symfony\Component\Console\Output\NullOutput;

class MigrationCommandTest extends TestCase
{
    /**
     * setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->command);
    }

    /**
     * Test that migrating without the `--no-lock` option will dispatch a dump shell
     *
     * @return void
     */
    public function testMigrateWithLock()
    {
        $argv = [
            '-c',
            'test',
        ];

        $this->command = $this->getMockCommand('MigrationsMigrateCommand');

        $this->command->expects($this->once())
            ->method('executeCommand');

        $this->command->run($argv, $this->getMockIo());
    }

    /**
     * Test that migrating with the `--no-lock` option will not dispatch a dump shell
     *
     * @return void
     */
    public function testMigrateWithNoLock()
    {
        $argv = [
            '-c',
            'test',
            '--no-lock',
        ];

        $this->command = $this->getMockCommand('MigrationsMigrateCommand');

        $this->command->expects($this->never())
            ->method('executeCommand');

        $this->command->run($argv, $this->getMockIo());
    }

    /**
     * Test that rolling back without the `--no-lock` option will dispatch a dump shell
     *
     * @return void
     */
    public function testRollbackWithLock()
    {
        $argv = [
            '-c',
            'test',
        ];

        $this->command = $this->getMockCommand('MigrationsRollbackCommand');

        $this->command->expects($this->once())
            ->method('executeCommand');

        $this->command->run($argv, $this->getMockIo());
    }

    /**
     * Test that rolling back with the `--no-lock` option will not dispatch a dump shell
     *
     * @return void
     */
    public function testRollbackWithNoLock()
    {
        $argv = [
            '-c',
            'test',
            '--no-lock',
        ];

        $this->command = $this->getMockCommand('MigrationsRollbackCommand');

        $this->command->expects($this->never())
            ->method('executeCommand');

        $this->command->run($argv, $this->getMockIo());
    }

    protected function getMockIo()
    {
        $output = new ConsoleOutput();
        $io = $this->getMockBuilder(ConsoleIo::class)
            ->setConstructorArgs([$output, $output, null, null])
            ->setMethods(['in'])
            ->getMock();

        return $io;
    }

    protected function getMockCommand($command)
    {
        $mockedMethods = [
            'executeCommand',
            'getApp',
            'getOutput',
        ];

        $mock = $this->getMockBuilder('\Migrations\Command\\' . $command)
        ->setMethods($mockedMethods)
        ->getMock();

        $mock->expects($this->any())
            ->method('getOutput')
            ->will($this->returnValue(new NullOutput()));

        $mock->expects($this->any())
            ->method('getApp')
            ->will($this->returnValue(new MigrationsDispatcher(PHINX_VERSION)));

        return $mock;
    }
}
