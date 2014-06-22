<?php

namespace Cake\Migrations\Command;

use Cake\Migrations\ConfigurationTrait;
use Phinx\Console\Command\Status as StatusCommand;

class Status extends StatusCommand {

	use ConfigurationTrait;

}
