<?php

namespace Migrations\Command;

use Migrations\ConfigurationTrait;
use Phinx\Console\Command\Status as StatusCommand;

class Status extends StatusCommand {

	use ConfigurationTrait;

}
