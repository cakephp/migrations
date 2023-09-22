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
use Exception;
use Migrations\CakeManager;
use Migrations\MigrationsDispatcher;
use Migrations\Test\CommandTester as TestCommandTester;
use Migrations\Test\TestCase\DriverConnectionTrait;
use PDO;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * MarkMigratedTest class
 */
class MarkMigratedTest extends TestCase
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
     * Instance of a CommandTester object
     *
     * @var \Symfony\Component\Console\Tester\CommandTester
     */
    protected $commandTester;

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

        $this->connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->connection->execute('DROP TABLE IF EXISTS numbers');

        $application = new MigrationsDispatcher('testing');
        $this->command = $application->find('mark_migrated');
        $this->commandTester = new CommandTester($this->command);
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
     * Test executing "mark_migration" in a standard way
     *
     * @return void
     */
    public function testExecute()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Migration `20150826191400` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Migration `20150724233100` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Migration `20150704160200` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );

        $result = $this->connection->selectQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);
        $this->assertEquals('20150724233100', $result[1]['version']);
        $this->assertEquals('20150826191400', $result[2]['version']);

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Skipping migration `20150704160200` (already migrated).',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Skipping migration `20150724233100` (already migrated).',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Skipping migration `20150826191400` (already migrated).',
            $this->commandTester->getDisplay()
        );

        $result = $this->connection->selectQuery()->select(['COUNT(*)'])->from('phinxlog')->execute();
        $this->assertSame(4, $result->fetchColumn(0));

        $config = $this->command->getConfig();
        $env = $this->command->getManager()->getEnvironment('default');
        $migrations = $this->command->getManager()->getMigrations('default');

        $manager = $this->getMockBuilder(CakeManager::class)
            ->onlyMethods(['getEnvironment', 'markMigrated', 'getMigrations'])
            ->setConstructorArgs([$config, new ArgvInput([]), new StreamOutput(fopen('php://memory', 'a', false))])
            ->getMock();

        $manager->expects($this->any())
            ->method('getEnvironment')->will($this->returnValue($env));
        $manager->expects($this->any())
            ->method('getMigrations')->will($this->returnValue($migrations));
        $manager
            ->method('markMigrated')->will($this->throwException(new Exception('Error during marking process')));

        $this->connection->execute('DELETE FROM phinxlog');

        $application = new MigrationsDispatcher('testing');
        $buggyCommand = $application->find('mark_migrated');
        $buggyCommand->setManager($manager);
        $buggyCommandTester = new TestCommandTester($buggyCommand);
        $buggyCommandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'An error occurred while marking migration `20150704160200` as migrated : Error during marking process',
            $buggyCommandTester->getDisplay()
        );
    }

    /**
     * Test executing "mark_migration" with deprecated `all` version
     *
     * @return void
     */
    public function testExecuteAll()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'version' => 'all',
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Migration `20150826191400` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Migration `20150724233100` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Migration `20150704160200` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'DEPRECATED: `all` or `*` as version is deprecated. Use `bin/cake migrations mark_migrated` instead',
            $this->commandTester->getDisplay()
        );

        $result = $this->connection->selectQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);
        $this->assertEquals('20150724233100', $result[1]['version']);
        $this->assertEquals('20150826191400', $result[2]['version']);

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'version' => 'all',
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Skipping migration `20150704160200` (already migrated).',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Skipping migration `20150724233100` (already migrated).',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Skipping migration `20150826191400` (already migrated).',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'DEPRECATED: `all` or `*` as version is deprecated. Use `bin/cake migrations mark_migrated` instead',
            $this->commandTester->getDisplay()
        );
    }

    public function testExecuteTarget()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--target' => '20150704160200',
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Migration `20150704160200` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );

        $result = $this->connection->selectQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--target' => '20150826191400',
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Skipping migration `20150704160200` (already migrated).',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Migration `20150724233100` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Migration `20150826191400` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );

        $result = $this->connection->selectQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);
        $this->assertEquals('20150724233100', $result[1]['version']);
        $this->assertEquals('20150826191400', $result[2]['version']);
        $result = $this->connection->selectQuery()->select(['COUNT(*)'])->from('phinxlog')->execute();
        $this->assertSame(3, $result->fetchColumn(0));

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--target' => '20150704160610',
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Migration `20150704160610` was not found !',
            $this->commandTester->getDisplay()
        );
    }

    public function testExecuteTargetWithExclude()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--target' => '20150724233100',
            '--exclude' => true,
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Migration `20150704160200` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );

        $result = $this->connection->selectQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--target' => '20150826191400',
            '--exclude' => true,
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Skipping migration `20150704160200` (already migrated).',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Migration `20150724233100` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );

        $result = $this->connection->selectQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);
        $this->assertEquals('20150724233100', $result[1]['version']);
        $result = $this->connection->selectQuery()->select(['COUNT(*)'])->from('phinxlog')->execute();
        $this->assertSame(2, $result->fetchColumn(0));

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--target' => '20150704160610',
            '--exclude' => true,
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Migration `20150704160610` was not found !',
            $this->commandTester->getDisplay()
        );
    }

    public function testExecuteTargetWithOnly()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--target' => '20150724233100',
            '--only' => true,
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Migration `20150724233100` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );

        $result = $this->connection->selectQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertEquals('20150724233100', $result[0]['version']);

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--target' => '20150826191400',
            '--only' => true,
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Migration `20150826191400` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );

        $result = $this->connection->selectQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertEquals('20150826191400', $result[1]['version']);
        $this->assertEquals('20150724233100', $result[0]['version']);
        $result = $this->connection->selectQuery()->select(['COUNT(*)'])->from('phinxlog')->execute();
        $this->assertSame(2, $result->fetchColumn(0));

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--target' => '20150704160610',
            '--only' => true,
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Migration `20150704160610` was not found !',
            $this->commandTester->getDisplay()
        );
    }

    public function testExecuteWithVersionAsArgument()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            'version' => '20150724233100',
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $this->assertStringContainsString(
            'Migration `20150724233100` successfully marked migrated !',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'DEPRECATED: VERSION as argument is deprecated. Use: ' .
            '`bin/cake migrations mark_migrated --target=VERSION --only`',
            $this->commandTester->getDisplay()
        );

        $result = $this->connection->selectQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertSame(1, count($result));
        $this->assertEquals('20150724233100', $result[0]['version']);
    }

    public function testExecuteInvalidUseOfOnlyAndExclude()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--exclude' => true,
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $result = $this->connection->selectQuery()->select(['COUNT(*)'])->from('phinxlog')->execute();
        $this->assertSame(0, $result->fetchColumn(0));
        $this->assertStringContainsString(
            'You should use `--exclude` OR `--only` (not both) along with a `--target` !',
            $this->commandTester->getDisplay()
        );

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--only' => true,
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $result = $this->connection->selectQuery()->select(['COUNT(*)'])->from('phinxlog')->execute();
        $this->assertSame(0, $result->fetchColumn(0));
        $this->assertStringContainsString(
            'You should use `--exclude` OR `--only` (not both) along with a `--target` !',
            $this->commandTester->getDisplay()
        );

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--target' => '20150724233100',
            '--only' => true,
            '--exclude' => true,
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $result = $this->connection->selectQuery()->select(['COUNT(*)'])->from('phinxlog')->execute();
        $this->assertSame(0, $result->fetchColumn(0));
        $this->assertStringContainsString(
            'You should use `--exclude` OR `--only` (not both) along with a `--target` !',
            $this->commandTester->getDisplay()
        );
    }
}
