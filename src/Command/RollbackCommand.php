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
use InvalidArgumentException;
use LogicException;
use Migrations\Config\ConfigInterface;
use Migrations\Migration\ManagerFactory;
use Throwable;

/**
 * Rollback command runs reverse actions of migrations
 */
class RollbackCommand extends Command
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
        return 'migrations rollback';
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
            'Rollback migrations to a specific migration',
            '',
            'Reverts the last migration or optionally to a specific migration',
            '',
            '<info>migrations rollback --connection secondary</info>',
            '<info>migrations rollback --connection secondary --target 003</info>',
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'The plugin to rollback migrations for',
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
            'help' => 'The target version to rollback to.',
        ])->addOption('date', [
            'short' => 'd',
            'help' => 'The date to rollback to',
        ])->addOption('count', [
            'short' => 'k',
            'help' => 'The number of migrations to rollback',
        ])->addOption('fake', [
            'help' => "Mark any migrations selected as run, but don't actually execute them",
            'boolean' => true,
        ])->addOption('force', [
            'help' => 'Force rollback to ignore breakpoints',
            'short' => 'f',
            'boolean' => true,
        ])->addOption('dry-run', [
            'help' => 'Dump queries to stdout instead of running them.',
            'short' => 'x',
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
        $event = $this->dispatchEvent('Migration.beforeRollback');
        if ($event->isStopped()) {
            return $event->getResult() ? self::CODE_SUCCESS : self::CODE_ERROR;
        }
        $result = $this->executeMigrations($args, $io);
        $this->dispatchEvent('Migration.afterRollback');

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
        $force = (bool)$args->getOption('force');
        $dryRun = (bool)$args->getOption('dry-run');

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
            'dry-run' => $dryRun,
        ]);
        $manager = $factory->createManager($io);
        $config = $manager->getConfig();

        $versionOrder = $config->getVersionOrder();
        $io->verbose('<info>using connection</info> ' . (string)$args->getOption('connection'));
        $io->verbose('<info>using paths</info> ' . $config->getMigrationPath());
        $io->verbose('<info>ordering by</info> ' . $versionOrder . ' time');

        if ($dryRun) {
            $io->info('DRY-RUN mode enabled');
        }
        if ($fake) {
            $io->out('<warning>warning</warning> performing fake rollbacks');
        }

        if ($date === null) {
            $targetMustMatch = true;
            $target = $version;
        } else {
            $targetMustMatch = false;
            $target = $this->getTargetFromDate($date);
        }

        try {
            // run the migrations
            $start = microtime(true);
            if ($count) {
                $manager->rollbackByCount($count, $force, $fake);
            } else {
                $manager->rollback($target, $force, $targetMustMatch, $fake);
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
        if (!$args->getOption('no-lock')) {
            $io->verbose('');
            $io->verbose('Dumping the current schema of the database to be used while baking a diff');
            $io->verbose('');

            $newArgs = DumpCommand::extractArgs($args);
            $exitCode = $this->executeCommand(DumpCommand::class, $newArgs, $io);
        }

        return $exitCode;
    }

    /**
     * Get Target from Date
     *
     * @param string|bool $date The date to convert to a target.
     * @throws \InvalidArgumentException
     * @return string The target
     */
    protected function getTargetFromDate(string|bool $date): string
    {
        // Narrow types as getOption() can return null|bool|string
        if (!is_string($date) || !preg_match('/^\d{4,14}$/', $date)) {
            throw new InvalidArgumentException('Invalid date. Format is YYYY[MM[DD[HH[II[SS]]]]].');
        }
        // what we need to append to the date according to the possible date string lengths
        $dateStrlenToAppend = [
            14 => '',
            12 => '00',
            10 => '0000',
            8 => '000000',
            6 => '01000000',
            4 => '0101000000',
        ];

        /** @var string $date */
        $dateLength = strlen($date);
        if (!isset($dateStrlenToAppend[$dateLength])) {
            throw new InvalidArgumentException('Invalid date. Format is YYYY[MM[DD[HH[II[SS]]]]].');
        }
        $target = $date . $dateStrlenToAppend[$dateLength];
        $dateTime = DateTime::createFromFormat('YmdHis', $target);
        if ($dateTime === false) {
            throw new InvalidArgumentException('Invalid date. Format is YYYY[MM[DD[HH[II[SS]]]]].');
        }

        return $dateTime->format('YmdHis');
    }
}
