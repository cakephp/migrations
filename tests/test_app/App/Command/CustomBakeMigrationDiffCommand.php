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
    public string $pathFragment = '';

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
            ->addOption('path-fragment')
            ->addOption('comparison');
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $pathFragment = $args->getOption('path-fragment');
        assert($pathFragment !== null);

        $this->pathFragment = 'config' . DS . $pathFragment . DS;

        return parent::execute($args, $io);
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
