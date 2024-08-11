<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Command;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\TestSuite\TestCase;
use Migrations\MigrationsDispatcher;
use Symfony\Component\Console\Output\NullOutput;

class MigrationCommandTest extends TestCase
{
    /**
     * @var \Migrations\MigrationsMigrateCommand
     */
    protected $command;

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
        $in = new StubConsoleInput([]);
        $output = new StubConsoleOutput();
        $io = $this->getMockBuilder(ConsoleIo::class)
            ->setConstructorArgs([$output, $output, $in])
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

        $mock = $this->getMockBuilder('Migrations\Command\\' . $command)
        ->onlyMethods($mockedMethods)
        ->getMock();

        $mock->expects($this->any())
            ->method('getOutput')
            ->willReturn(new NullOutput());

        $mock->expects($this->any())
            ->method('getApp')
            ->willReturn(new MigrationsDispatcher(PHINX_VERSION));

        return $mock;
    }
}
