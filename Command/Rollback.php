<?php

namespace Migrations\Command;

use Migrations\ConfigurationTrait;
use Phinx\Console\Command\Rollback as RollbackCommand;

class Rollback extends RollbackCommand {

	use ConfigurationTrait;

}
