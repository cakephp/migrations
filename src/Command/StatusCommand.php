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
use Cake\Console\Exception\StopException;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use Migrations\Config\Config;
use Migrations\Migration\Manager;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Status command for built in backend
 */
class StatusCommand extends Command
{
    /**
     * Exit code for when status command is run and there are missing migrations
     *
     * @var int
     */
    public const CODE_STATUS_MISSING = 2;

    /**
     * Exit code for when status command is run and there are no missing migations,
     * but does have down migrations
     *
     * @var int
     */
    public const CODE_STATUS_DOWN = 3;

    /**
     * The default name added to the application command list
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'migrations status';
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
            'The <info>status</info> command prints a list of all migrations, along with their current status',
            '',
            '<info>migrations status -c secondary</info>',
            '<info>migrations status -c secondary  -f json</info>',
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'The plugin to run migrations for',
        ])->addOption('connection', [
            'short' => 'c',
            'help' => 'The datasource connection to use',
            'default' => 'default',
        ])->addOption('source', [
            'short' => 's',
            'help' => 'The folder under src/Config that migrations are in',
            'default' => 'Migrations',
        ])->addOption('format', [
            'short' => 'f',
            'help' => 'The output format: text or json. Defaults to text.',
            'choices' => ['text', 'json'],
            'default' => 'text',
        ]);

        return $parser;
    }

    /**
     * Generate a configuration object for the migrations operation.
     *
     * @param \Cake\Console\Arguments $args The console arguments
     * @return \Migrations\Config\Config The generated config instance.
     */
    protected function getConfig(Arguments $args): Config
    {
        $folder = (string)$args->getOption('source');

        // Get the filepath for migrations and seeds(not implemented yet)
        $dir = ROOT . '/config/' . $folder;
        if (defined('CONFIG')) {
            $dir = CONFIG . $folder;
        }
        $plugin = $args->getOption('plugin');
        if ($plugin && is_string($plugin)) {
            $dir = Plugin::path($plugin) . 'config/' . $folder;
        }

        // Get the phinxlog table name. Plugins have separate migration history.
        // The names and separate table history is something we could change in the future.
        $table = 'phinxlog';
        if ($plugin && is_string($plugin)) {
            $prefix = Inflector::underscore($plugin) . '_';
            $prefix = str_replace(['\\', '/', '.'], '_', $prefix);
            $table = $prefix . $table;
        }
        $templatePath = dirname(__DIR__) . DS . 'templates' . DS;
        $connectionName = (string)$args->getOption('connection');

        // TODO this all needs to go away. But first Environment and Manager need to work
        // with Cake's ConnectionManager.
        $connectionConfig = ConnectionManager::getConfig($connectionName);
        if (!$connectionConfig) {
            throw new StopException("Could not find connection `{$connectionName}`");
        }

        /** @var array<string, string> $connectionConfig */
        $adapter = $connectionConfig['scheme'] ?? null;
        $adapterConfig = [
            'adapter' => $adapter,
            'connection' => $connectionName,
            'database' => $connectionConfig['database'],
            'migration_table' => $table,
        ];

        $configData = [
            'paths' => [
                'migrations' => $dir,
            ],
            'templates' => [
                'file' => $templatePath . 'Phinx/create.php.template',
            ],
            'migration_base_class' => 'Migrations\AbstractMigration',
            'environment' => $adapterConfig,
            // TODO do we want to support the DI container in migrations?
        ];

        return new Config($configData);
    }

    /**
     * Get the migration manager for the current CLI options and application configuration.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @return \Migrations\Migration\Manager
     */
    protected function getManager(Arguments $args): Manager
    {
        $config = $this->getConfig($args);

        return new Manager($config, new ArgvInput(), new StreamOutput(STDOUT));
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
        /** @var string|null $format */
        $format = $args->getOption('format');
        $migrations = $this->getManager($args)->printStatus($format);

        switch ($format) {
            case 'json':
                $flags = 0;
                if ($args->getOption('verbose')) {
                    $flags = JSON_PRETTY_PRINT;
                }
                $migrationString = (string)json_encode($migrations, $flags);
                $io->out($migrationString);
                break;
            default:
                $this->display($migrations, $io);
                break;
        }

        return Command::CODE_SUCCESS;
    }

    /**
     * Print migration status to stdout.
     *
     * @param array $migrations
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     */
    protected function display(array $migrations, ConsoleIo $io): void
    {
        if (!empty($migrations)) {
            $rows = [];
            $rows[] = ['Status', 'Migration ID', 'Migration Name'];

            foreach ($migrations as $migration) {
                $status = $migration['status'] === 'up' ? '<info>up</info>' : '<error>down</error>';
                $name = $migration['name'] ?
                    '<comment>' . $migration['name'] . '</comment>' :
                    '<error>** MISSING **</error>';

                $missingComment = '';
                if (!empty($migration['missing'])) {
                    $missingComment = '<error>** MISSING **</error>';
                }
                $rows[] = [$status, sprintf('%14.0f ', $migration['id']), $name . $missingComment];
            }
            $io->helper('table')->output($rows);
        } else {
            $msg = 'There are no available migrations. Try creating one using the <info>create</info> command.';
            $io->err('');
            $io->err($msg);
            $io->err('');
        }
    }
}
