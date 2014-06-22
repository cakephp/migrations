<?php

namespace Migrations\Command;

use Migrations\ConfigurationTrait;
use Phinx\Console\Command\Create as CreateCommand;

class Create extends CreateCommand {

	use ConfigurationTrait;
}
