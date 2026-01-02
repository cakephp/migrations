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
use Migrations\Config\ConfigInterface;
use Migrations\Migration\ManagerFactory;
use Migrations\Util\Util;

/**
 * Seed reset command removes seeds from the execution log
 */
class SeedResetCommand extends Command
{
    /**
     * The default name added to the application command list
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'seeds reset';
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
            'The <info>reset</info> command removes seed execution records from the log',
            'allowing seeds to be re-run without the --force flag.',
            '',
            '<info>seeds reset</info>',
            '<info>seeds reset --seed Users</info>',
            '<info>seeds reset --seed Users,Posts</info>',
            '<info>seeds reset --plugin Demo</info>',
            '<info>seeds reset -c secondary</info>',
        ])->addOption('seed', [
            'help' => 'Comma-separated list of specific seeds to reset. Resets all seeds if not specified.',
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'The plugin to reset seeds for',
        ])->addOption('connection', [
            'short' => 'c',
            'help' => 'The datasource connection to use',
            'default' => 'default',
        ])->addOption('source', [
            'short' => 's',
            'help' => 'The folder under config that seeds are in',
            'default' => ConfigInterface::DEFAULT_SEED_FOLDER,
        ])->addOption('dry-run', [
            'short' => 'd',
            'help' => 'Show what would be reset without actually doing it',
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
        $factory = new ManagerFactory([
            'plugin' => $args->getOption('plugin'),
            'source' => $args->getOption('source'),
            'connection' => $args->getOption('connection'),
            'dry-run' => (bool)$args->getOption('dry-run'),
        ]);

        $manager = $factory->createManager($io);
        $config = $manager->getConfig();

        if ($config->isDryRun()) {
            $io->info('DRY-RUN mode enabled');
        }

        $io->verbose('<info>using connection</info> ' . (string)$args->getOption('connection'));
        $io->verbose('<info>using paths</info> ' . $config->getSeedPath());

        $seeds = $manager->getSeeds();
        $adapter = $manager->getEnvironment()->getAdapter();

        // Filter seeds if --seed option is specified
        $seedOption = $args->getOption('seed');
        $seedsToReset = $seeds;

        if ($seedOption) {
            $requestedSeeds = array_map('trim', explode(',', (string)$seedOption));
            $seedsToReset = [];

            foreach ($requestedSeeds as $requestedSeed) {
                $normalizedName = $manager->normalizeSeedName($requestedSeed, $seeds);
                if ($normalizedName === null) {
                    $io->error("Seed `{$requestedSeed}` does not exist.");

                    return self::CODE_ERROR;
                }
                $seedsToReset[$normalizedName] = $seeds[$normalizedName];
            }
        }

        if (empty($seedsToReset)) {
            $io->warning('No seeds to reset.');

            return self::CODE_SUCCESS;
        }

        // Show what will be reset and ask for confirmation
        $io->out('');
        $resetAllMessage = $seedOption ? '<info>The following seeds will be reset:</info>' : '<info>All seeds will be reset:</info>';
        $io->out($resetAllMessage);
        foreach ($seedsToReset as $seed) {
            $io->out('  - ' . Util::getSeedDisplayName($seed->getName()));
        }
        $io->out('');

        if (!$config->isDryRun()) {
            $continue = $io->askChoice('Do you want to continue?', ['y', 'n'], 'n');
            if ($continue !== 'y') {
                $io->warning('Reset operation aborted.');

                return self::CODE_SUCCESS;
            }
        }

        // Reset the seeds
        $count = 0;
        foreach ($seedsToReset as $seed) {
            $seedName = Util::getSeedDisplayName($seed->getName());
            if ($manager->isSeedExecuted($seed)) {
                if (!$config->isDryRun()) {
                    $adapter->removeSeedFromLog($seed);
                }
                $io->info("Reset: {$seedName} seed");
                $count++;
            } else {
                $io->verbose("Skipped (not executed): {$seedName} seed");
            }
        }

        $io->out('');
        if ($config->isDryRun()) {
            $io->success("DRY-RUN: Would reset {$count} seed(s).");
        } else {
            $io->success("Reset {$count} seed(s).");
        }

        return self::CODE_SUCCESS;
    }
}
