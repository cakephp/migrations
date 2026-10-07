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
namespace Migrations\Test\TestCase\Command;

use Cake\Core\Exception\MissingPluginException;
use Cake\Datasource\ConnectionManager;
use Migrations\Test\TestCase\TestCase;

/**
 * MarkMigratedTest class
 */
class MarkMigratedTest extends TestCase
{
    /**
     * Instance of a Cake Connection object
     *
     * @var \Cake\Database\Connection
     */
    protected $connection;

    /**
     * setup method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = ConnectionManager::get('test');
        // Drop both legacy and unified tables
        $this->connection->execute('DROP TABLE IF EXISTS migrator_phinxlog');
        $this->connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->connection->execute('DROP TABLE IF EXISTS cake_migrations');
        $this->connection->execute('DROP TABLE IF EXISTS numbers');
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->connection->execute('DROP TABLE IF EXISTS migrator_phinxlog');
        $this->connection->execute('DROP TABLE IF EXISTS phinxlog');
        $this->connection->execute('DROP TABLE IF EXISTS cake_migrations');
        $this->connection->execute('DROP TABLE IF EXISTS numbers');
    }

    /**
     * Test executing "mark_migration" in a standard way
     *
     * @return void
     */
    public function testExecute(): void
    {
        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations');

        $this->assertExitSuccess();
        $this->assertOutputContains(
            'Migration `20150826191400` successfully marked migrated !',
        );
        $this->assertOutputContains(
            'Migration `20150724233100` successfully marked migrated !',
        );
        $this->assertOutputContains(
            'Migration `20150704160200` successfully marked migrated !',
        );

        $result = $this->connection->selectQuery()->select(['*'])->from($this->getMigrationsTableName())->execute()->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);
        $this->assertEquals('20150724233100', $result[1]['version']);
        $this->assertEquals('20150826191400', $result[2]['version']);

        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations');

        $this->assertExitSuccess();
        $this->assertOutputContains(
            'Skipping migration `20150704160200` (already migrated).',
        );
        $this->assertOutputContains(
            'Skipping migration `20150724233100` (already migrated).',
        );
        $this->assertOutputContains(
            'Skipping migration `20150826191400` (already migrated).',
        );

        $result = $this->connection->selectQuery()->select(['COUNT(*)'])->from($this->getMigrationsTableName())->execute();
        $this->assertEquals(4, $result->fetchColumn(0));
    }

    public function testExecuteTarget(): void
    {
        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --target=20150704160200');
        $this->assertExitSuccess();

        $this->assertOutputContains(
            'Migration `20150704160200` successfully marked migrated !',
        );

        $result = $this->connection->selectQuery()
            ->select(['*'])
            ->from($this->getMigrationsTableName())
            ->execute()
            ->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);

        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --target=20150826191400');
        $this->assertExitSuccess();

        $this->assertOutputContains(
            'Skipping migration `20150704160200` (already migrated).',
        );
        $this->assertOutputContains(
            'Migration `20150724233100` successfully marked migrated !',
        );
        $this->assertOutputContains(
            'Migration `20150826191400` successfully marked migrated !',
        );

        $result = $this->connection->selectQuery()
            ->select(['*'])
            ->from($this->getMigrationsTableName())
            ->execute()
            ->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);
        $this->assertEquals('20150724233100', $result[1]['version']);
        $this->assertEquals('20150826191400', $result[2]['version']);

        $result = $this->connection->selectQuery()
            ->select(['COUNT(*)'])
            ->from($this->getMigrationsTableName())
            ->execute();
        $this->assertEquals(3, $result->fetchColumn(0));
    }

    public function testTargetNotFound(): void
    {
        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --target=20150704160610');
        $this->assertExitError();

        $this->assertErrorContains(
            'Migration `20150704160610` was not found !',
        );
    }

    public function testExecuteTargetWithExclude(): void
    {
        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --target=20150724233100 --exclude');
        $this->assertExitSuccess();
        $this->assertOutputContains(
            'Migration `20150704160200` successfully marked migrated !',
        );

        $result = $this->connection->selectQuery()
            ->select(['*'])
            ->from($this->getMigrationsTableName())
            ->execute()
            ->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);

        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --target=20150826191400 --exclude');

        $this->assertOutputContains(
            'Skipping migration `20150704160200` (already migrated).',
        );
        $this->assertOutputContains(
            'Migration `20150724233100` successfully marked migrated !',
        );

        $result = $this->connection->selectQuery()
            ->select(['*'])
            ->from($this->getMigrationsTableName())
            ->execute()
            ->fetchAll('assoc');
        $this->assertEquals('20150704160200', $result[0]['version']);
        $this->assertEquals('20150724233100', $result[1]['version']);

        $result = $this->connection->selectQuery()
            ->select(['COUNT(*)'])
            ->from($this->getMigrationsTableName())
            ->execute();
        $this->assertEquals(2, $result->fetchColumn(0));
    }

    public function testExecuteTargetWithExcludeNotFound(): void
    {
        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --target=20150704160610 --exclude');
        $this->assertExitError();

        $this->assertErrorContains(
            'Migration `20150704160610` was not found !',
        );
    }

    public function testExecuteTargetWithOnly(): void
    {
        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --target=20150724233100 --only');
        $this->assertExitSuccess();

        $this->assertOutputContains(
            'Migration `20150724233100` successfully marked migrated !',
        );

        $result = $this->connection->selectQuery()
            ->select(['*'])
            ->from($this->getMigrationsTableName())
            ->execute()
            ->fetchAll('assoc');
        $this->assertEquals('20150724233100', $result[0]['version']);

        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --target=20150826191400 --only');

        $this->assertOutputContains(
            'Migration `20150826191400` successfully marked migrated !',
        );

        $result = $this->connection->selectQuery()
            ->select(['*'])
            ->from($this->getMigrationsTableName())
            ->execute()
            ->fetchAll('assoc');
        $this->assertEquals('20150826191400', $result[1]['version']);
        $this->assertEquals('20150724233100', $result[0]['version']);
        $result = $this->connection->selectQuery()
            ->select(['COUNT(*)'])
            ->from($this->getMigrationsTableName())
            ->execute();
        $this->assertEquals(2, $result->fetchColumn(0));
    }

    public function testExecuteTargetWithOnlyNotFound(): void
    {
        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --target=20150704160610 --only');
        $this->assertExitError();

        $this->assertErrorContains(
            'Migration `20150704160610` was not found !',
        );
    }

    public function testExecuteInvalidUseOfExclude(): void
    {
        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --exclude');

        $this->assertExitError();
        $this->assertErrorContains(
            'You should use `--exclude` OR `--only` (not both) along with a `--target` !',
        );
    }

    public function testExecuteInvalidUseOfOnly(): void
    {
        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --only');

        $this->assertExitError();
        $this->assertErrorContains(
            'You should use `--exclude` OR `--only` (not both) along with a `--target` !',
        );
    }

    public function testExecuteInvalidUseOfOnlyAndExclude(): void
    {
        $this->exec('migrations mark_migrated --connection=test --source=TestsMigrations --only --exclude');

        $this->assertExitError();
        $this->assertErrorContains(
            'You should use `--exclude` OR `--only` (not both) along with a `--target` !',
        );
    }

    public function testExecutePluginInvalid(): void
    {
        try {
            $this->exec('migrations mark_migrated -c test --plugin NotThere');
            $this->fail('Should raise an error or exit with an error');
        } catch (MissingPluginException) {
            $this->assertTrue(true);
        }
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertNotContains('not_there_phinxlog', $tables);
    }

    public function testExecutePlugin(): void
    {
        $this->loadPlugins(['Migrator']);
        $this->exec('migrations mark_migrated -c test --plugin Migrator --only --target 20211001000000');
        $this->assertExitSuccess();
        $this->assertOutputContains('`20211001000000` successfully marked migrated');

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $tables = $connection->getSchemaCollection()->listTables();
        $this->assertContains($this->getMigrationsTableName('Migrator'), $tables);
    }
}
