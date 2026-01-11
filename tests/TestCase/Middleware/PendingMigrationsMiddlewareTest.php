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
namespace Migrations\Test\TestCase\Middleware;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Exception\CakeException;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Migrations\Config\Config;
use Migrations\Middleware\PendingMigrationsMiddleware;
use Migrations\Migration\Manager;
use TestApp\Http\TestRequestHandler;

class PendingMigrationsMiddlewareTest extends TestCase
{
    private string $db;

    private ConsoleIo $io;

    /**
     * Setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $connection = ConnectionManager::get('test');

        $config = $connection->config();
        $this->db = $config['database'];
        $this->io = new ConsoleIo(
            new StubConsoleOutput(),
            new StubConsoleOutput(),
            new StubConsoleInput([]),
        );
    }

    public function tearDown(): void
    {
        parent::tearDown();
        ConnectionManager::drop('custom');
        ConnectionManager::drop('default');
    }

    /**
     * @return void
     */
    public function testAppMigrationsFail(): void
    {
        $middleware = new PendingMigrationsMiddleware();

        $request = new ServerRequest();
        $handler = new TestRequestHandler(function ($req) {
            return new Response();
        });

        $this->expectException(CakeException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage('Pending migrations need to be run for app:');

        $middleware->process($request, $handler);
    }

    /**
     * @return void
     */
    public function testAppMigrationsSuccess(): void
    {
        $middleware = new PendingMigrationsMiddleware();

        $config = [
            'paths' => [
               'migrations' => ROOT . DS . 'config' . DS . 'Migrations' . DS,
            ],
            'environment' => [
               'database' => $this->db,
               'connection' => 'default',
               'migration_table' => 'phinxlog',
            ],
        ];
        $config = new Config($config);
        $manager = new Manager($config, $this->io);
        $manager->migrate(null, true);

        $request = new ServerRequest();
        $handler = new TestRequestHandler(function ($req) {
            return new Response();
        });
        $result = $middleware->process($request, $handler);
        $this->assertInstanceOf(Response::class, $result);
    }

    /**
     * @return void
     */
    public function testAppAndPluginsMigrationsFail(): void
    {
        $this->loadPlugins(['Migrator']);

        $middleware = new PendingMigrationsMiddleware([
            'plugins' => true,
        ]);

        $request = new ServerRequest();
        $handler = new TestRequestHandler(function ($req) {
            return new Response();
        });

        $this->expectException(CakeException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage('Pending migrations need to be run for Migrator:');

        $middleware->process($request, $handler);
    }

    /**
     * @return void
     */
    public function testAppAndPluginsMigrationsSuccess(): void
    {
        $this->loadPlugins(['Migrator']);

        $middleware = new PendingMigrationsMiddleware([
            'plugins' => true,
        ]);

        $config = [
            'paths' => [
                'migrations' => ROOT . DS . 'Plugin' . DS . 'Migrator' . DS . 'config' . DS . 'Migrations' . DS,
            ],
            'environment' => [
                'connection' => 'default',
                'database' => $this->db,
                'migration_table' => 'migrator_phinxlog',
            ],
        ];
        $config = new Config($config);
        $manager = new Manager($config, $this->io);
        $manager->migrate(null, true);

        $request = new ServerRequest();
        $handler = new TestRequestHandler(function ($req) {
            return new Response();
        });

        $result = $middleware->process($request, $handler);
        $this->assertInstanceOf(Response::class, $result);
    }
}
