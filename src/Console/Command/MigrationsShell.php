<?php

namespace Migrations\Console\Command;

use Migrations\MigrationsDispatcher;
use Cake\Console\Shell;

class MigrationsShell extends Shell {

	public function getOptionParser() {
		return parent::getOptionParser()
			->addOption('plugin', ['short' => 'p'])
			->addOption('target', ['short' => 't'])
			->addOption('connection', ['short' => 'c']);
	}

	public function initialize() {
		if (!defined('PHINX_VERSION')) {
			define('PHINX_VERSION', (0 === strpos('@PHINX_VERSION@', '@PHINX_VERSION')) ? '0.3.5' : '@PHINX_VERSION@');
		}
		parent::initialize();
	}

	public function main() {
		array_shift($_SERVER['argv']);
		$_SERVER['argv']--;
		$app = new MigrationsDispatcher(PHINX_VERSION);
		$app->run();
	}

/**
 * Display the help in the correct format
 *
 * @param string $command
 * @return void
 */
	protected function _displayHelp($command) {
		$this->main();
	}

}
