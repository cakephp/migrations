<?php

namespace Cake\Migrations\Command;

use Cake\Migrations\ConfigurationTrait;
use Phinx\Console\Command\Create as CreateCommand;

class Create extends CreateCommand {

	use ConfigurationTrait;

	protected $_requiresEnv = false;

}
