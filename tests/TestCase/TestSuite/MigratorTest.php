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
namespace Migrations\Test\TestCase\TestSuite;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Migrations\Test\MigratorTestTrait;
use Migrations\TestSuite\Migrator;

class MigratorTest extends TestCase
{
    use MigratorTestTrait;

    public function setUp(): void
    {
        parent::setUp();

        $this->restore = $GLOBALS['__PHPUNIT_BOOTSTRAP'];
        unset($GLOBALS['__PHPUNIT_BOOTSTRAP']);
        $this->setDummyConnections();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $GLOBALS['__PHPUNIT_BOOTSTRAP'] = $this->restore;
    }

    private function fetchMigrationsInDB(string $dbTable): array
    {
        return ConnectionManager::get('test')
            ->newQuery()
            ->select('migration_name')
            ->from($dbTable)
            ->execute()
            ->fetch();
    }

    public function testMigrate(): void
    {
        $this->markTestSkipped('This test drops all tables resulting in other tests failing.');
        (new Migrator())->run();

        $appMigrations = $this->fetchMigrationsInDB('phinxlog');
        $fooPluginMigrations = $this->fetchMigrationsInDB('foo_plugin_phinxlog');
        $barPluginMigrations = $this->fetchMigrationsInDB('bar_plugin_phinxlog');

        $this->assertSame(['MarkMigratedTest'], $appMigrations);
        $this->assertSame(['FooMigration'], $fooPluginMigrations);
        $this->assertSame(['BarMigration'], $barPluginMigrations);

        $letters = TableRegistry::getTableLocator()->get('Letters');
        $this->assertSame('test', $letters->getConnection()->configName());
    }

    public function testDropTablesForMissingMigrations(): void
    {
        $this->markTestSkipped('This test drops all tables resulting in other tests failing.');
        $migrator = new Migrator();
        $migrator->run();

        $connection = ConnectionManager::get('test');
        $connection->insert('phinxlog', ['version' => 1, 'migration_name' => 'foo',]);

        $count = $connection->newQuery()->select('version')->from('phinxlog')->execute()->count();
        $this->assertSame(2, $count);

        $migrator->run();
        $count = $connection->newQuery()->select('version')->from('phinxlog')->execute()->count();
        $this->assertSame(1, $count);
    }
}
