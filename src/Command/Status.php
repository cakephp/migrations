<?php

namespace Cake\Migrations\Command;

use Cake\Migrations\ConfigurationTrait;
use Phinx\Console\Command\Status as StatusCommand;
use Symfony\Component\Console\Input\InputArgument;

class Status extends StatusCommand {

	use ConfigurationTrait;

/**
 * {@inheritdoc}
 */
	protected function configure() {
		$this->setName('status')
			->setDescription('Show migration status')
			->addOption('--format', '-f', InputArgument::OPTIONAL, 'The output format: text or json. Defaults to text.')
			->setHelp('prints a list of all migrations, along with their current status')
			->addOption('--plugin', '-p', InputArgument::OPTIONAL, 'The plugin containing the migrations')
			->addOption('--connection', '-c', InputArgument::OPTIONAL, 'The datasource connection to use');
	}

}
