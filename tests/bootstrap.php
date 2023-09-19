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

use Bake\BakePlugin;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Routing\Router;
use Cake\TestSuite\Fixture\SchemaLoader;
use Migrations\MigrationsPlugin;
use Snapshot\Plugin as SnapshotPlugin;
use TestBlog\Plugin as TestBlogPlugin;
use function Cake\Core\env;

$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);
    throw new Exception('Cannot find the root of the application, unable to run tests');
};
$root = $findRoot(__FILE__);
unset($findRoot);
chdir($root);

require_once 'vendor/autoload.php';

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('CORE_PATH', $root . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS);
define('ROOT', $root . DS . 'tests' . DS . 'test_app');
define('APP_DIR', 'App');
define('APP', ROOT . DS . 'App' . DS);
define('TMP', sys_get_temp_dir() . DS . 'cake-migrations' . DS);
define('CACHE', TMP . DS . 'cache' . DS);
if (!defined('CONFIG')) {
    define('CONFIG', ROOT . DS . 'config' . DS);
}

// phpcs:disable
@mkdir(CACHE);
// phpcs:enable

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'TestApp',
    'encoding' => 'UTF-8',
    'paths' => [
        'plugins' => [ROOT . DS . 'Plugin' . DS],
        'templates' => [ROOT . DS . 'App' . DS . 'Template' . DS],
    ],
]);

Cache::setConfig([
    '_cake_core_' => [
        'engine' => 'File',
        'prefix' => 'cake_core_',
        'serialize' => true,
    ],
    '_cake_model_' => [
        'engine' => 'File',
        'prefix' => 'cake_model_',
        'serialize' => true,
        'path' => TMP,
    ],
]);

// Store initial state
Router::reload();

if (!getenv('DB_URL')) {
    putenv('DB_URL=sqlite://127.0.0.1/cakephp_test');
}
if (!getenv('DB')) {
    $dsn = getenv('DB_URL');
    $db = 'sqlite';
    if (preg_match('#^(.+)://#', $dsn, $matches)) {
        $db = $matches[1];
    }
    if ($db === 'postgres') {
        $db = 'pgsql';
    }
    putenv('DB=' . $db);
}
ConnectionManager::setConfig('test', [
    'cacheMetadata' => false,
    'url' => getenv('DB_URL'),
]);

if (getenv('DB_URL_COMPARE') !== false) {
    ConnectionManager::setConfig('test_comparisons', [
        'cacheMetadata' => false,
        'url' => getenv('DB_URL_COMPARE'),
    ]);
}

Plugin::getCollection()->add(new MigrationsPlugin());
Plugin::getCollection()->add(new BakePlugin());
Plugin::getCollection()->add(new SnapshotPlugin());
Plugin::getCollection()->add(new TestBlogPlugin());

if (!defined('PHINX_VERSION')) {
    define('PHINX_VERSION', strpos('@PHINX_VERSION@', '@PHINX_VERSION') === 0 ? 'UNKNOWN' : '@PHINX_VERSION@');
}

// Create test database schema
if (env('FIXTURE_SCHEMA_METADATA')) {
    $loader = new SchemaLoader();
    $loader->loadInternalFile(env('FIXTURE_SCHEMA_METADATA'), 'test');
}
