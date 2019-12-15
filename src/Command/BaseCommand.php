<?php

namespace Migrations\Command;

use Symfony\Component\Console\Command\Command;

abstract class BaseCommand extends Command
{
    const CODE_SUCCESS = 0;
    const CODE_ERROR = 1;
}
