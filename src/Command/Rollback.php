<?php

namespace Cake\Migrations\Command;

use Cake\Migrations\ConfigurationTrait;
use Phinx\Console\Command\Rollback as RollbackCommand;

class Rollback extends RollbackCommand {

	use ConfigurationTrait;

}
