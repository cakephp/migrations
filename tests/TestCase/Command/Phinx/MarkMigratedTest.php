<?php
declare(strict_types=1);

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
namespace Migrations\Test\TestCase\Command\Phinx;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\CakeManager;
use Migrations\MigrationsDispatcher;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\StreamOutput;
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
     * @var \PDO|object
     */
    protected $pdo;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->connection = ConnectionManager::get('test');
        $this->connection->connect();
        $this->pdo = $this->connection->getDriver()->getConnection();
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
        $this->connection->getDriver()->setConnection($this->pdo);
        $this->connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->connection->execute('DROP TABLE IF EXISTS numbers');
        unset($this->connection, $this->commandTester, $this->command);
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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertSame('20150704160200', $result[0]['version']);
        $this->assertSame('20150724233100', $result[1]['version']);
        $this->assertSame('20150826191400', $result[2]['version']);

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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->count();
        $this->assertSame(3, $result);

        $config = $this->command->getConfig();
        $env = $this->command->getManager()->getEnvironment('default');
        $migrations = $this->command->getManager()->getMigrations('default');

        $manager = $this->getMockBuilder(CakeManager::class)
            ->setMethods(['getEnvironment', 'markMigrated', 'getMigrations'])
            ->setConstructorArgs([$config, new ArgvInput([]), new StreamOutput(fopen('php://memory', 'a', false))])
            ->getMock();

        $manager->expects($this->any())
            ->method('getEnvironment')->will($this->returnValue($env));
        $manager->expects($this->any())
            ->method('getMigrations')->will($this->returnValue($migrations));
        $manager
            ->method('markMigrated')->will($this->throwException(new \Exception('Error during marking process')));

        $this->connection->execute('DELETE FROM phinxlog');

        $application = new MigrationsDispatcher('testing');
        $buggyCommand = $application->find('mark_migrated');
        $buggyCommand->setManager($manager);
        $buggyCommandTester = new \Migrations\Test\CommandTester($buggyCommand);
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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertSame('20150704160200', $result[0]['version']);
        $this->assertSame('20150724233100', $result[1]['version']);
        $this->assertSame('20150826191400', $result[2]['version']);

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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertSame('20150704160200', $result[0]['version']);

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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertSame('20150704160200', $result[0]['version']);
        $this->assertSame('20150724233100', $result[1]['version']);
        $this->assertSame('20150826191400', $result[2]['version']);
        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->count();
        $this->assertSame(3, $result);

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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertSame('20150704160200', $result[0]['version']);

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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertSame('20150704160200', $result[0]['version']);
        $this->assertSame('20150724233100', $result[1]['version']);
        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->count();
        $this->assertSame(2, $result);

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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertSame('20150724233100', $result[0]['version']);

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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertSame('20150826191400', $result[1]['version']);
        $this->assertSame('20150724233100', $result[0]['version']);
        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->count();
        $this->assertSame(2, $result);

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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->fetchAll('assoc');
        $this->assertSame(1, count($result));
        $this->assertSame('20150724233100', $result[0]['version']);
    }

    public function testExecuteInvalidUseOfOnlyAndExclude()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--exclude' => true,
            '--connection' => 'test',
            '--source' => 'TestsMigrations',
        ]);

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->count();
        $this->assertSame(0, $result);
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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->count();
        $this->assertSame(0, $result);
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

        $result = $this->connection->newQuery()->select(['*'])->from('phinxlog')->execute()->count();
        $this->assertSame(0, $result);
        $this->assertStringContainsString(
            'You should use `--exclude` OR `--only` (not both) along with a `--target` !',
            $this->commandTester->getDisplay()
        );
    }
}
