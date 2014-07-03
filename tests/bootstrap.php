<?php

require dirname(__DIR__) . '/vendor/cakephp/cakephp/src/basics.php';
require dirname(__DIR__) . '/vendor/autoload.php';

define('APP', sys_get_temp_dir());
define('DS', DIRECTORY_SEPARATOR);
Cake\Core\Configure::write('App', [
	'namespace' => 'App'
]);
