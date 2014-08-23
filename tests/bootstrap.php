<?php

require dirname(__DIR__) . '/vendor/cakephp/cakephp/src/basics.php';
require dirname(__DIR__) . '/vendor/autoload.php';

define('DS', DIRECTORY_SEPARATOR);
define('APP', sys_get_temp_dir());
define('ROOT', dirname(__DIR__) . DS);
Cake\Core\Configure::write('App', [
	'namespace' => 'App'
]);
