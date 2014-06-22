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
			mkdir($dir, 777, true);
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
					'adapter' => 'pgsql',
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

	protected function execute(InputInterface $input, OutputInterface $output) {
		if (!$input->hasOption('environment') && !empty($this->_requiresEnv)) {
			$input->setOption('environment', 'default');
		}
		parent::execute($input, $output);
	}
}
