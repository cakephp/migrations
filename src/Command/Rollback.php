<?php

namespace Cake\Migrations\Command;

use Cake\Migrations\ConfigurationTrait;
use Phinx\Console\Command\Rollback as RollbackCommand;
use Symfony\Component\Console\Input\InputArgument;

class Rollback extends RollbackCommand {

	use ConfigurationTrait;

/**
 * {@inheritdoc}
 */
	protected function configure() {
		$this->setName('rollback')
			->setDescription('Rollback the last or to a specific migration')
			->addOption('--target', '-t', InputArgument::OPTIONAL, 'The version number to rollback to')
			->setHelp('reverts the last migration, or optionally up to a specific version')
			->addOption('--plugin', '-p', InputArgument::OPTIONAL, 'The plugin containing the migrations')
			->addOption('--connection', '-c', InputArgument::OPTIONAL, 'The datasource connection to use');
	}

}
