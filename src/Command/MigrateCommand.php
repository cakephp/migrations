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
use Cake\Event\EventDispatcherTrait;
use DateTime;
use Exception;
use LogicException;
use Migrations\Config\ConfigInterface;
use Migrations\Migration\ManagerFactory;
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
        ])->addOption('count', [
            'short' => 'k',
            'help' => 'The number of migrations to run',
        ])->addOption('fake', [
            'help' => "Mark any migrations selected as run, but don't actually execute them",
            'boolean' => true,
        ])->addOption('dry-run', [
            'short' => 'x',
            'help' => 'Dump queries to stdout instead of executing them',
            'boolean' => true,
        ])->addOption('no-lock', [
            'help' => 'If present, no lock file will be generated after migrating',
            'boolean' => true,
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

        $count = $args->getOption('count') !== null ? (int)$args->getOption('count') : null;
        if ($count !== null && $count < 1) {
            throw new LogicException('Count must be > 0.');
        }
        if ($count && $date) {
            throw new LogicException('Can only use one of `--count` or `--date` options at a time.');
        }
        if ($version && $date) {
            throw new LogicException('Can only use one of `--version` or `--date` options at a time.');
        }

        $factory = new ManagerFactory([
            'plugin' => $args->getOption('plugin'),
            'source' => $args->getOption('source'),
            'connection' => $args->getOption('connection'),
            'dry-run' => (bool)$args->getOption('dry-run'),
        ]);

        $manager = $factory->createManager($io);
        $config = $manager->getConfig();

        $versionOrder = $config->getVersionOrder();

        if ($config->isDryRun()) {
            $io->info('DRY-RUN mode enabled');
        }
        $io->verbose('<info>using connection</info> ' . (string)$args->getOption('connection'));
        $io->verbose('<info>using paths</info> ' . $config->getMigrationPath());
        $io->verbose('<info>ordering by</info> ' . $versionOrder . ' time');

        if ($fake) {
            $io->out('<warning>warning</warning> performing fake migrations');
        }

        try {
            // run the migrations
            $start = microtime(true);
            if ($date !== null) {
                $manager->migrateToDateTime(new DateTime((string)$date), $fake);
            } else {
                $manager->migrate($version, $fake, $count);
            }
            $end = microtime(true);
        } catch (Exception $e) {
            $io->err('<error>' . $e->getMessage() . '</error>');
            $io->verbose($e->getTraceAsString());

            return self::CODE_ERROR;
        } catch (Throwable $e) {
            $io->err('<error>' . $e->getMessage() . '</error>');
            $io->verbose($e->getTraceAsString());

            return self::CODE_ERROR;
        }

        $io->comment('All Done. Took ' . sprintf('%.4fs', $end - $start));
        $io->out('');

        $exitCode = self::CODE_SUCCESS;

        // Run dump command to generate lock file
        if (!$args->getOption('no-lock') && !$args->getOption('dry-run')) {
            $io->verbose('');
            $io->verbose('Dumping the current schema of the database to be used while baking a diff');
            $io->verbose('');

            $newArgs = DumpCommand::extractArgs($args);
            $exitCode = $this->executeCommand(DumpCommand::class, $newArgs, $io);
        }

        return $exitCode;
    }
}
