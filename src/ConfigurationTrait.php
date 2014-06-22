<?php

namespace Cake\Migrations;

use Cake\Datasource\ConnectionManager;
use Phinx\Config\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

trait ConfigurationTrait {

	protected $_configuration;

	public function getConfig() {
		if ($this->_configuration) {
			return $this->_configuration;
		}

		$dir = APP . 'Config' . DS . 'Migrations';
		if (!is_dir($dir)) {
			mkdir($dir, 1777, true);
		}

		$config = ConnectionManager::config('default');
		return $this->_configuration = new Config([
			'paths' => [
				'migrations' => $dir
			],
			'environments' => [
				'default_migration_table' => 'phinxlog',
				'default_database' => 'default',
				'default' => [
					'adapter' => $this->_getAdapterName($config['driver']),
					'host' => $config['host'],
					'user' => $config['login'],
					'pass' => $config['password'],
					'port' => isset($config['port']) ? $config['port'] : null,
					'name' => $config['database'],
					'charset' => $config['encoding']
				]
			]
		]);
	}

	protected function _getAdapterName($driver) {
		switch ($driver) {
			case 'Cake\Database\Driver\Mysql':
			case is_subclass_of($driver, 'Cake\Database\Driver\Mysql') :
				return 'mysql';
			case 'Cake\Database\Driver\Postgres':
			case is_subclass_of($driver, 'Cake\Database\Driver\Postgres') :
				return 'pgsql';
			case 'Cake\Database\Driver\Sqlite':
			case is_subclass_of($driver, 'Cake\Database\Driver\Sqlite') :
				return 'sqlite';
			case 'Cake\Database\Driver\SqlServer':
			case is_subclass_of($driver, 'Cake\Database\Driver\SqlServer') :
				return 'sqlsrv';
		}

		throw new \InvalidArgumentexception('Could not infer databse type from driver');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if ($input->hasOption('environment')) {
			$input->setOption('environment', 'default');
		}
		parent::execute($input, $output);
	}
}
