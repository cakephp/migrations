<?php

namespace TestApp\Command;

use Cake\Console\Arguments;
use Cake\Core\Plugin;
use Migrations\Command\BakeMigrationDiffCommand;

class CustomSimpleBakeMigrationDiffCommand extends BakeMigrationDiffCommand
{
    public $pathFragment = 'config/MigrationsDiffSimple/';

    /**
     * {@inheritDoc}
     */
    public static function defaultName(): string
    {
        return 'customSimple bake migration_diff';
    }

    protected function getDumpSchema(Arguments $args)
    {
        $diffConfigFolder = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS . 'simple' . DS;
        $diffDumpPath = $diffConfigFolder . 'schema-dump-test_comparisons_' . env('DB') . '.lock';

        return unserialize(file_get_contents($diffDumpPath));
    }
}