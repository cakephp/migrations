<?php
namespace Migrations\Test\TestCase\Shell;

use Cake\TestSuite\TestCase;

class MigrationShellTest extends TestCase
{

    /**
     * setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $inputOutput = $this->getMockBuilder('\Cake\Console\ConsoleIo')
            ->disableOriginalConstructor()
            ->getMock();

        $mockedMethods = [
            'dispatchShell',
        ];

        $this->shell = $this->getMockBuilder('\Migrations\Shell\MigrationsShell')
            ->setMethods($mockedMethods)
            ->setConstructorArgs([$inputOutput])
            ->getMock();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        unset($this->shell);
    }

    /**
     * Test that migrating without the `--no-lock` option will dispatch a dump shell
     *
     * @return void
     */
    public function testMigrateWithLock()
    {
        $argv = [
            'migrate',
            '-c',
            'test'
        ];

        $this->shell->expects($this->once())
            ->method('dispatchShell');

        $this->shell->runCommand($argv);
    }

    /**
     * Test that migrating with the `--no-lock` option will not dispatch a dump shell
     *
     * @return void
     */
    public function testMigrateWithNoLock()
    {
        $argv = [
            'migrate',
            '-c',
            'test',
            '--no-lock'
        ];

        $this->shell->expects($this->never())
            ->method('dispatchShell');

        $this->shell->runCommand($argv);
    }

    /**
     * Test that rolling back without the `--no-lock` option will dispatch a dump shell
     *
     * @return void
     */
    public function testRollbackWithLock()
    {
        $argv = [
            'rollback',
            '-c',
            'test'
        ];

        $this->shell->expects($this->once())
            ->method('dispatchShell');

        $this->shell->runCommand($argv);
    }

    /**
     * Test that rolling back with the `--no-lock` option will not dispatch a dump shell
     *
     * @return void
     */
    public function testRollbackWithNoLock()
    {
        $argv = [
            'rollback',
            '-c',
            'test',
            '--no-lock'
        ];

        $this->shell->expects($this->never())
            ->method('dispatchShell');

        $this->shell->runCommand($argv);
    }
}
