<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Migrations\Config\ConfigInterface;
use Migrations\Migration\ManagerFactory;
use Migrations\Util\TableFinder;

/**
 * Dump command class.
 * A "dump" is a snapshot of a database at a given point in time. It is stored in a
 * .lock file in the same folder as migrations files.
 */
class DumpCommand extends Command
{
    protected string $connection;

    /**
     * The default name added to the application command list
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'migrations dump';
    }

    /**
     * Extract options for the dump command from another migrations option parser.
     *
     * @param \Cake\Console\Arguments $args
     * @return array<int|string, mixed>
     */
    public static function extractArgs(Arguments $args): array
    {
        /** @var array<int|string, mixed> $newArgs */
        $newArgs = [];
        if ($args->getOption('connection')) {
            $newArgs[] = '-c';
            $newArgs[] = $args->getOption('connection');
        }
        if ($args->getOption('plugin')) {
            $newArgs[] = '-p';
            $newArgs[] = $args->getOption('plugin');
        }
        if ($args->getOption('source')) {
            $newArgs[] = '-s';
            $newArgs[] = $args->getOption('source');
        }

        return $newArgs;
    }

    /**
     * Configure the option parser
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The option parser to configure
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription([
            'Dumps the current scheam of the database to be used while baking a diff',
            '',
            '<info>migrations dump -c secondary</info>',
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'The plugin to dump migrations for',
        ])->addOption('connection', [
            'short' => 'c',
            'help' => 'The datasource connection to use',
            'default' => 'default',
        ])->addOption('source', [
            'short' => 's',
            'help' => 'The folder under src/Config that migrations are in',
            'default' => ConfigInterface::DEFAULT_MIGRATION_FOLDER,
        ]);

        return $parser;
    }

    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $factory = new ManagerFactory([
            'plugin' => $args->getOption('plugin'),
            'source' => $args->getOption('source'),
            'connection' => $args->getOption('connection'),
        ]);
        $config = $factory->createConfig();
        $path = $config->getMigrationPath();
        $connectionName = (string)$config->getConnection();
        $connection = ConnectionManager::get($connectionName);
        assert($connection instanceof Connection);

        $collection = $connection->getSchemaCollection();
        $options = [
            'require-table' => false,
            'plugin' => $args->getOption('plugin'),
        ];
        // The connection property is used by the trait methods.
        $this->connection = $connectionName;
        $finder = new TableFinder($connectionName);
        $tables = $finder->getTablesToBake($collection, $options);

        $dump = [];
        if ($tables) {
            foreach ($tables as $table) {
                $schema = $collection->describe($table);
                $dump[$table] = $schema;
            }
        }

        $filePath = $path . DS . 'schema-dump-' . $connectionName . '.lock';
        $io->out("<info>Writing dump file `{$filePath}`...</info>");
        if (file_put_contents($filePath, serialize($dump))) {
            $io->out("<info>Dump file `{$filePath}` was successfully written</info>");

            return self::CODE_SUCCESS;
        }
        $io->out("<error>An error occurred while writing dump file `{$filePath}`</error>");

        return self::CODE_ERROR;
    }
}
