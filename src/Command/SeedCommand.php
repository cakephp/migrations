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
    use EventDispatcherTrait;

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Run migration seeds.';
    }

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
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
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
     * @return int|null The exit code or null for success
     */
    public function execute(): ?int
    {
        $event = $this->dispatchEvent('Migration.beforeSeed');
        if ($event->isStopped()) {
            return $event->getResult() ? self::CODE_SUCCESS : self::CODE_ERROR;
        }
        $result = $this->executeSeeds();
        $this->dispatchEvent('Migration.afterSeed');

        return $result;
    }

    /**
     * Execute seeds based on console inputs.
     *
     * @return int|null The exit code or null for success
     */
    protected function executeSeeds(): ?int
    {
        $factory = new ManagerFactory([
            'plugin' => $this->args->getOption('plugin'),
            'source' => $this->args->getOption('source'),
            'connection' => $this->args->getOption('connection'),
            'dry-run' => (bool)$this->args->getOption('dry-run'),
        ]);
        $manager = $factory->createManager($this->io);
        $config = $manager->getConfig();
        // Get seed names from arguments
        $seeds = [];
        if ($this->args->hasArgument('seed')) {
            $seedArg = $this->args->getArgument('seed');
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
        $fake = (bool)$this->args->getOption('fake');
        if ($config->isDryRun()) {
            $this->io->info('DRY-RUN mode enabled');
        }
        if ($fake) {
            $this->io->warning('performing fake seeding');
        }
        $this->io->verbose('<info>using connection</info> ' . $this->args->getOption('connection'));
        $this->io->verbose('<info>using paths</info> ' . $config->getMigrationPath());
        $this->io->verbose('<info>ordering by</info> ' . $versionOrder . ' time');

        $start = microtime(true);
        if (!$seeds) {
            // Get all available seeds and ask for confirmation
            try {
                $availableSeeds = $manager->getSeeds();
            } catch (Throwable $e) {
                $this->io->err('<error>Failed to load seeds: ' . $e->getMessage() . '</error>');
                $this->io->verbose($e->getTraceAsString());

                return static::CODE_ERROR;
            }

            if (!$availableSeeds) {
                $this->io->warning('No seeds found.');

                return self::CODE_SUCCESS;
            }

            // Skip confirmation in quiet mode
            if ($this->io->level() > ConsoleIo::QUIET) {
                $force = (bool)$this->args->getOption('force');

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

                $this->io->out('');
                if (!$willRun) {
                    $this->io->out('All seeds have already been executed. Use --force to re-run.');
                    $this->io->out('');

                    return self::CODE_SUCCESS;
                }

                $this->io->out('<info>The following seeds will be executed:</info>');
                foreach ($willRun as $name) {
                    $this->io->out('  - ' . $name);
                }
                $this->io->out('');
                if ($force) {
                    $this->io->out('<warning>Warning:</warning> Running with --force will re-execute all seeds,');
                    $this->io->out('potentially creating duplicate data. Ensure your seeds are idempotent.');
                }
                $this->io->out('');

                // Ask for confirmation
                $continue = $this->io->askChoice('Do you want to continue?', ['y', 'n'], 'n');
                if ($continue !== 'y') {
                    $this->io->warning('Seed operation aborted.');

                    return self::CODE_SUCCESS;
                }
            }

            // run all the seed(ers)
            $manager->seed(null, (bool)$this->args->getOption('force'), $fake);
        } else {
            // run seed(ers) specified as arguments
            foreach ($seeds as $seed) {
                $manager->seed(trim($seed), (bool)$this->args->getOption('force'), $fake);
            }
        }
        $end = microtime(true);
        $this->io->comment('All Done. Took ' . sprintf('%.4fs', $end - $start));

        return self::CODE_SUCCESS;
    }
}
