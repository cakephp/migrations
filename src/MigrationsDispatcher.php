<?php

namespace Cake\Migrations;

use Cake\Migrations\Command;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationsDispatcher extends Application {

/**
 * Class Constructor.
 *
 * Initialize the Phinx console application.
 *
 * @param string $version The Application Version
 */
	public function __construct($version) {
		parent::__construct('Migrations plugin, based on Phinx by Rob Morgan.', $version);
		$this->add(new Command\Create());
		$this->add(new Command\Migrate());
		$this->add(new Command\Rollback());
		$this->add(new Command\Status());
	}

}
