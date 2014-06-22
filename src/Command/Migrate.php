<?php

namespace Cake\Migrations\Command;

use Cake\Migrations\ConfigurationTrait;
use Phinx\Console\Command\Migrate as MigrateCommand;

class Migrate extends MigrateCommand {

	use ConfigurationTrait;

}
