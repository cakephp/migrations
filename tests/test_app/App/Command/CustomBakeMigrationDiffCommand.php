<?php
declare(strict_types=1);

namespace TestApp\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Plugin;
use Migrations\Command\BakeMigrationDiffCommand;
use function Cake\Core\env;

class CustomBakeMigrationDiffCommand extends BakeMigrationDiffCommand
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'custom bake migration_diff';
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->addOption('test-target-folder')
            ->addOption('comparison');
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $testTargetFolder = $args->getOption('test-target-folder');
        assert($testTargetFolder !== null);

        $this->pathFragment = 'config' . DS . $testTargetFolder . DS;

        return parent::execute($args, $io);
    }

    public function getPath(Arguments $args): string
    {
        // Avoids having to use the `source` option, as it would be passed down to
        // other commands, causing a migration files lookup in the folder where
        // the new migration has been baked, causing an error as a class with the
        // same name will already exist from loading/applying the comparison diff.

        $path = ROOT . DS . $this->pathFragment;
        if ($this->plugin) {
            $path = $this->_pluginPath($this->plugin) . $this->pathFragment;
        }

        return str_replace('/', DS, $path);
    }

    protected function getDumpSchema(Arguments $args): array
    {
        $comparison = $args->getOption('comparison');
        assert($comparison !== null);

        $diffConfigFolder = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS . $comparison . DS;
        $diffDumpPath = $diffConfigFolder . 'schema-dump-test_comparisons_' . env('DB') . '.lock';

        return unserialize(file_get_contents($diffDumpPath));
    }
}
