<?php

namespace Migrations;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use Phinx\Config\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

trait ConfigurationTrait {

	protected $_configuration;

	protected $_input;

	public function getConfig() {
		if ($this->_configuration) {
			return $this->_configuration;
		}

		$dir = ROOT . DS . 'config' . DS . 'Migrations';
		$plugin = null;

		if ($this->_input->getOption('plugin')) {
			$plugin = $this->_input->getOption('plugin');
			$dir = Plugin::path($plugin) . 'config' . DS . 'Migrations';
		}

		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}

		$plugin = $plugin ? Inflector::underscore($plugin) . '_' : '';

		$connection = 'default';
		if ($this->_input->getOption('connection')) {
			$connection = $this->_input->getOption('connection');
		}

		$config = ConnectionManager::config($connection);
		return $this->_configuration = new Config([
			'paths' => [
				'migrations' => $dir
			],
			'environments' => [
				'default_migration_table' => $plugin . 'phinxlog',
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
			case 'Cake\Database\Driver\Sqlserver':
			case is_subclass_of($driver, 'Cake\Database\Driver\Sqlserver') :
				return 'sqlsrv';
		}

		throw new \InvalidArgumentexception('Could not infer database type from driver');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->_input = $input;
		$this->addOption('--environment', '-e', InputArgument::OPTIONAL);
		$input->setOption('environment', 'default');
		parent::execute($input, $output);
	}

	public function bootstrap(InputInterface $input, OutputInterface $output) {
		parent::bootstrap($input, $output);
		$connection = $this->getManager()->getEnvironment('default')->getAdapter()->getConnection();
		ConnectionManager::get('default')->driver()->connection($connection);
	}

}
