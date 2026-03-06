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
use Migrations\Config\ConfigInterface;
use Migrations\Migration\ManagerFactory;
use Migrations\Util\Util;
use Throwable;

/**
 * Seed command runs seeder scripts
 */
class SeedCommand extends Command
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
        return 'seeds run';
    }

    /**
     * Configure the option parser
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The option parser to configure
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $description = [
            'Seed the database with data',
            '',
            'Runs a seeder script that can populate the database with data, or run mutations:',
            '',
            '<info>seeds run Posts</info>',
            '<info>seeds run Users,Posts</info>',
            '<info>seeds run --plugin Demo</info>',
            '<info>seeds run --connection secondary</info>',
            '',
            'Runs all seeds if no seed names are specified. When running all seeds',
            'in an interactive terminal, a confirmation prompt is shown.',
        ];

        $parser->setDescription($description)
            ->addArgument('seed', [
                'help' => 'The name(s) of the seed(s) to run (comma-separated for multiple). Run all seeds if not specified.',
                'required' => false,
            ])
            ->addOption('plugin', [
                'short' => 'p',
                'help' => 'The plugin to run seeds in',
            ])
            ->addOption('connection', [
                'short' => 'c',
                'help' => 'The datasource connection to use',
                'default' => 'default',
            ])
            ->addOption('dry-run', [
                'short' => 'd',
                'help' => 'Dump queries to stdout instead of executing them',
                'boolean' => true,
            ])
            ->addOption('source', [
                'short' => 's',
                'default' => ConfigInterface::DEFAULT_SEED_FOLDER,
                'help' => 'The folder where your seeds are.',
            ])
            ->addOption('force', [
                'short' => 'f',
                'help' => 'Force re-running seeds that have already been executed',
                'boolean' => true,
            ])
            ->addOption('fake', [
                'help' => 'Mark seeds as executed without actually running them',
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
        $event = $this->dispatchEvent('Migration.beforeSeed');
        if ($event->isStopped()) {
            return $event->getResult() ? self::CODE_SUCCESS : self::CODE_ERROR;
        }
        $result = $this->executeSeeds($args, $io);
        $this->dispatchEvent('Migration.afterSeed');

        return $result;
    }

    /**
     * Execute seeds based on console inputs.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    protected function executeSeeds(Arguments $args, ConsoleIo $io): ?int
    {
        $factory = new ManagerFactory([
            'plugin' => $args->getOption('plugin'),
            'source' => $args->getOption('source'),
            'connection' => $args->getOption('connection'),
            'dry-run' => (bool)$args->getOption('dry-run'),
        ]);

        $manager = $factory->createManager($io);
        $config = $manager->getConfig();

        // Get seed names from arguments
        $seeds = [];
        if ($args->hasArgument('seed')) {
            $seedArg = $args->getArgument('seed');
            if ($seedArg !== null) {
                // Split by comma to support comma-separated list
                $seedList = explode(',', $seedArg);
                foreach ($seedList as $seed) {
                    $trimmed = trim($seed);
                    if ($trimmed !== '') {
                        $seeds[] = $trimmed;
                    }
                }
            }
        }

        $versionOrder = $config->getVersionOrder();

        $fake = (bool)$args->getOption('fake');

        if ($config->isDryRun()) {
            $io->info('DRY-RUN mode enabled');
        }
        if ($fake) {
            $io->warning('performing fake seeding');
        }
        $io->verbose('<info>using connection</info> ' . (string)$args->getOption('connection'));
        $io->verbose('<info>using paths</info> ' . $config->getMigrationPath());
        $io->verbose('<info>ordering by</info> ' . $versionOrder . ' time');

        $start = microtime(true);
        if (!$seeds) {
            // Get all available seeds and ask for confirmation
            try {
                $availableSeeds = $manager->getSeeds();
            } catch (Throwable $e) {
                $io->err('<error>Failed to load seeds: ' . $e->getMessage() . '</error>');
                $io->verbose($e->getTraceAsString());

                return static::CODE_ERROR;
            }

            if (!$availableSeeds) {
                $io->warning('No seeds found.');

                return self::CODE_SUCCESS;
            }

            // Skip confirmation in quiet mode
            if ($io->level() > ConsoleIo::QUIET) {
                $force = (bool)$args->getOption('force');

                // Determine which seeds will actually run
                $willRun = [];
                foreach ($availableSeeds as $seed) {
                    $displayName = Util::getSeedDisplayName($seed->getName());
                    if ($seed->isIdempotent()) {
                        $willRun[] = $displayName . ' <info>(idempotent)</info>';
                    } elseif ($force || !$manager->isSeedExecuted($seed)) {
                        $willRun[] = $displayName;
                    }
                }

                $io->out('');
                if (!$willRun) {
                    $io->out('All seeds have already been executed. Use --force to re-run.');
                    $io->out('');

                    return self::CODE_SUCCESS;
                }

                $io->out('<info>The following seeds will be executed:</info>');
                foreach ($willRun as $name) {
                    $io->out('  - ' . $name);
                }
                $io->out('');
                if ($force) {
                    $io->out('<warning>Warning:</warning> Running with --force will re-execute all seeds,');
                    $io->out('potentially creating duplicate data. Ensure your seeds are idempotent.');
                }
                $io->out('');

                // Ask for confirmation
                $continue = $io->askChoice('Do you want to continue?', ['y', 'n'], 'n');
                if ($continue !== 'y') {
                    $io->warning('Seed operation aborted.');

                    return self::CODE_SUCCESS;
                }
            }

            // run all the seed(ers)
            $manager->seed(null, (bool)$args->getOption('force'), $fake);
        } else {
            // run seed(ers) specified as arguments
            foreach ($seeds as $seed) {
                $manager->seed(trim($seed), (bool)$args->getOption('force'), $fake);
            }
        }
        $end = microtime(true);

        $io->comment('All Done. Took ' . sprintf('%.4fs', $end - $start));

        return self::CODE_SUCCESS;
    }
}
