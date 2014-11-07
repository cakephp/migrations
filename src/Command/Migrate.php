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
namespace Migrations\Command;

use Migrations\ConfigurationTrait;
use Phinx\Console\Command\Migrate as MigrateCommand;
use Symfony\Component\Console\Input\InputArgument;

class Migrate extends MigrateCommand {

	use ConfigurationTrait;

/**
 * {@inheritdoc}
 */
	protected function configure() {
		$this->setName('migrate')
			->setDescription('Migrate the database')
			->addOption('--target', '-t', InputArgument::OPTIONAL, 'The version number to migrate to')
			->setHelp('runs all available migrations, optionally up to a specific version')
			->addOption('--plugin', '-p', InputArgument::OPTIONAL, 'The plugin containing the migrations')
			->addOption('--connection', '-c', InputArgument::OPTIONAL, 'The datasource connection to use')
			->addOption('--source', '-s', InputArgument::OPTIONAL, 'The folder where migration are in');
	}

}
