<?php
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

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Routing\Router;

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

require_once 'vendor/cakephp/cakephp/src/basics.php';
require_once 'vendor/autoload.php';

define('CORE_PATH', $root . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS);
define('ROOT', $root . DS . 'tests' . DS . 'test_app' . DS);
define('APP_DIR', 'App');
define('APP', ROOT . 'App' . DS);
define('TMP', sys_get_temp_dir() . DS);
define('CACHE', sys_get_temp_dir() . DS . 'cache' . DS);
if (!defined('CONFIG')) {
    define('CONFIG', ROOT . DS . 'config' . DS);
}
Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'TestApp',
    'encoding' => 'UTF-8',
    'paths' => [
        'plugins' => [ROOT . 'Plugin' . DS],
        'templates' => [ROOT . 'App' . DS . 'Template' . DS],
    ],
]);

Cake\Cache\Cache::setConfig([
    '_cake_core_' => [
        'engine' => 'File',
        'prefix' => 'cake_core_',
        'serialize' => true,
        'path' => TMP,
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

if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite://127.0.0.1/cakephp_test');
}
if (!getenv('DB')) {
    putenv('DB=sqlite');
}
ConnectionManager::setConfig('test', ['url' => getenv('db_dsn')]);

if (getenv('db_dsn_compare') !== false) {
    ConnectionManager::setConfig('test_comparisons', ['url' => getenv('db_dsn_compare')]);
}

Plugin::getCollection()->add(new \Migrations\Plugin());
Plugin::getCollection()->add(new \TestBlog\Plugin());

if (!defined('PHINX_VERSION')) {
    define('PHINX_VERSION', (0 === strpos('@PHINX_VERSION@', '@PHINX_VERSION')) ? 'UNKNOWN' : '@PHINX_VERSION@');
}
