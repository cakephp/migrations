<?php

namespace Migrations\Command;

use Migrations\ConfigurationTrait;
use Phinx\Console\Command\Migrate as MigrateCommand;

class Migrate extends MigrateCommand {

	use ConfigurationTrait;

}
