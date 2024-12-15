<?php
declare(strict_types=1);

namespace Migrations\Middleware;

use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Core\Exception\CakeException;
use Cake\Core\InstanceConfigTrait;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Hash;
use Migrations\Config\Config;
use Migrations\Migration\Manager;
use Migrations\Util\Util;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PendingMigrationsMiddleware implements MiddlewareInterface
{
    use InstanceConfigTrait;

    protected const SKIP_QUERY_KEY = 'skip-middleware-check';

    protected array $_defaultConfig = [
        'paths' => [
            'migrations' => ROOT . DS . 'config' . DS . 'Migrations' . DS,
        ],
        'environment' => [
            'connection' => 'default',
            'migration_table' => 'phinxlog',
        ],
        'app' => null,
        'plugins' => null,
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        if (!empty($config['plugins']) && $config['plugins'] === true) {
            $config['plugins'] = Plugin::loaded();
        }

        $this->setConfig($config);
    }

    /**
     * Process method.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The request handler.
     * @throws \Cake\Core\Exception\CakeException
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!Configure::read('debug') || $this->isSkipped($request)) {
            return $handler->handle($request);
        }

        $pendingMigrations = $this->pendingMigrations();
        if (!$pendingMigrations) {
            return $handler->handle($request);
        }

        $message = sprintf('Pending migrations need to be run for %s:', implode(', ', array_keys($pendingMigrations))) . PHP_EOL;
        $message .= '`' . implode('`,' . PHP_EOL . '`', $pendingMigrations) . '`';

        throw new CakeException($message, 503);
    }

    /**
     * @return array<string, string>
     */
    protected function pendingMigrations(): array
    {
        $pending = [];
        if (!$this->checkAppMigrations()) {
            $pending['app'] = 'bin/cake migrations migrate';
        }

        /** @var array<string> $plugins */
        $plugins = (array)$this->_config['plugins'];
        foreach ($plugins as $plugin) {
            if (!$this->checkPluginMigrations($plugin)) {
                $pending[$plugin] = 'bin/cake migrations migrate -p ' . $plugin;
            }
        }

        return $pending;
    }

    /**
     * @return bool
     */
    protected function checkAppMigrations(): bool
    {
        if ($this->_config['app'] === false) {
            return true;
        }

        $connection = ConnectionManager::get($this->_config['environment']['connection']);
        $database = $connection->config()['database'];
        $this->_config['environment']['database'] = $database;

        $config = new Config($this->_config);
        $manager = new Manager($config, new ConsoleIo());

        $migrations = $manager->getMigrations();
        foreach ($migrations as $migration) {
            if (!$manager->isMigrated($migration->getVersion())) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $plugin
     * @return bool
     */
    protected function checkPluginMigrations(string $plugin): bool
    {
        $connection = ConnectionManager::get($this->_config['environment']['connection']);
        $database = $connection->config()['database'];
        $this->_config['environment']['database'] = $database;

        $pluginPath = Plugin::path($plugin);
        if (!is_dir($pluginPath . 'config' . DS . 'Migrations' . DS)) {
            return true;
        }

        $config = [
            'paths' => [
                'migrations' => $pluginPath . 'config' . DS . 'Migrations' . DS,
            ],
        ] + $this->_config;

        $table = Util::tableName($plugin);

        $config['environment']['migration_table'] = $table;

        $managerConfig = new Config($config);
        $manager = new Manager($managerConfig, new ConsoleIo());

        $migrations = $manager->getMigrations();
        foreach ($migrations as $migration) {
            if (!$manager->isMigrated($migration->getVersion())) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return bool
     */
    protected function isSkipped(ServerRequestInterface $request): bool
    {
        return (bool)Hash::get($request->getQueryParams(), static::SKIP_QUERY_KEY);
    }
}
