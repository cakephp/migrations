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
use Cake\Event\EventDispatcherTrait;
use Cake\Utility\Inflector;
use DateTime;
use Exception;
use Migrations\Config\Config;
use Migrations\Config\ConfigInterface;
use Migrations\Migration\Manager;
use Throwable;

/**
 * Migrate command runs migrations
 */
class MigrateCommand extends Command
{
    /**
     * @use \Cake\Event\EventDispatcherTrait<\Migrations\Command\MigrateCommand>
     */
    use EventDispatcherTrait;

    /**
     * The default name added to the application command list
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'migrations migrate';
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
            'Apply migrations to a SQL datasource',
            '',
            'Will run all available migrations, optionally up to a specific version',
            '',
            '<info>migrations migrate --connection secondary</info>',
            '<info>migrations migrate --connection secondary --target 003</info>',
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'The plugin to run migrations for',
        ])->addOption('connection', [
            'short' => 'c',
            'help' => 'The datasource connection to use',
            'default' => 'default',
        ])->addOption('source', [
            'short' => 's',
            'default' => ConfigInterface::DEFAULT_MIGRATION_FOLDER,
            'help' => 'The folder where your migrations are',
        ])->addOption('target', [
            'short' => 't',
            'help' => 'The target version to migrate to.',
        ])->addOption('date', [
            'short' => 'd',
            'help' => 'The date to migrate to',
        ])->addOption('fake', [
            'help' => "Mark any migrations selected as run, but don't actually execute them",
            'boolean' => true,
        ])->addOption('no-lock', [
            'help' => 'If present, no lock file will be generated after migrating',
            'boolean' => true,
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
        $folder = $args->getOption('source');

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
            'dryrun' => $args->getOption('dry-run'),
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
     * @param \Cake\Console\ConsoleIo $io The command io.
     * @return \Migrations\Migration\Manager
     */
    protected function getManager(Arguments $args, ConsoleIo $io): Manager
    {
        $config = $this->getConfig($args);

        return new Manager($config, $io);
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
        $event = $this->dispatchEvent('Migration.beforeMigrate');
        if ($event->isStopped()) {
            return $event->getResult() ? self::CODE_SUCCESS : self::CODE_ERROR;
        }
        $result = $this->executeMigrations($args, $io);
        $this->dispatchEvent('Migration.afterMigrate');

        return $result;
    }

    /**
     * Execute migrations based on console inputs.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    protected function executeMigrations(Arguments $args, ConsoleIo $io): ?int
    {
        $version = $args->getOption('target') !== null ? (int)$args->getOption('target') : null;
        $date = $args->getOption('date');
        $fake = (bool)$args->getOption('fake');

        $manager = $this->getManager($args, $io);
        $config = $manager->getConfig();

        $versionOrder = $config->getVersionOrder();
        $io->out('<info>using connection</info> ' . (string)$args->getOption('connection'));
        $io->out('<info>ordering by</info> ' . $versionOrder . ' time');

        if ($fake) {
            $io->out('<warning>warning</warning> performing fake migrations');
        }

        try {
            // run the migrations
            $start = microtime(true);
            if ($date !== null) {
                $manager->migrateToDateTime(new DateTime((string)$date), $fake);
            } else {
                $manager->migrate($version, $fake);
            }
            $end = microtime(true);
        } catch (Exception $e) {
            $io->err('<error>' . $e->getMessage() . '</error>');
            $io->out($e->getTraceAsString(), 1, ConsoleIo::VERBOSE);

            return self::CODE_ERROR;
        } catch (Throwable $e) {
            $io->err('<error>' . $e->getMessage() . '</error>');
            $io->out($e->getTraceAsString(), 1, ConsoleIo::VERBOSE);

            return self::CODE_ERROR;
        }

        $io->out('');
        $io->out('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');

        // Run dump command to generate lock file
        /* TODO(mark) port this in
        if (
            isset($this->argv[1]) && in_array($this->argv[1], ['migrate', 'rollback'], true) &&
            !in_array('--no-lock', $this->argv, true) &&
            $exitCode === 0
        ) {
            $newArgs = [];
            if (!empty($args->getOption('connection'))) {
                $newArgs[] = '-c';
                $newArgs[] = $args->getOption('connection');
            }

            if (!empty($args->getOption('plugin'))) {
                $newArgs[] = '-p';
                $newArgs[] = $args->getOption('plugin');
            }

            $io->out('');
            $io->out('Dumps the current schema of the database to be used while baking a diff');
            $io->out('');

            $dumpExitCode = $this->executeCommand(MigrationsDumpCommand::class, $newArgs, $io);
        }

        if (isset($dumpExitCode) && $exitCode === 0 && $dumpExitCode !== 0) {
            $exitCode = 1;
        }

        return $exitCode;
        */

        return self::CODE_SUCCESS;
    }
}
