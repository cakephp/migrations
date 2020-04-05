<?php

namespace TestApp\Command;

use Cake\Console\Arguments;
use Cake\Core\Plugin;
use Migrations\Command\BakeMigrationDiffCommand;

class CustomBakeMigrationDiffCommand extends BakeMigrationDiffCommand
{
    public $pathFragment = 'config/MigrationsDiff/';

    protected function getDumpSchema(Arguments $args)
    {
        $diffConfigFolder = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS;
        $diffDumpPath = $diffConfigFolder . 'schema-dump-test_comparisons_' . env('DB') . '.lock';

        return unserialize(file_get_contents($diffDumpPath));
    }
}