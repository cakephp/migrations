<?php
declare(strict_types=1);

namespace TestApp\Command;

use Cake\Console\Arguments;
use Cake\Core\Plugin;
use Migrations\Command\BakeMigrationDiffCommand;

class CustomRemoveBakeMigrationDiffCommand extends BakeMigrationDiffCommand
{
    public $pathFragment = 'config/MigrationsDiffAddRemove/';

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'customRemove bake migration_diff';
    }

    protected function getDumpSchema(Arguments $args)
    {
        $diffConfigFolder = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS . 'addremove' . DS;
        $diffDumpPath = $diffConfigFolder . 'schema-dump-test_comparisons_' . env('DB') . '.lock';

        return unserialize(file_get_contents($diffDumpPath));
    }
}
