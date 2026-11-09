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
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Reset the seed execution state.';
    }

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
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
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
     * @return int|null The exit code or null for success
     */
    public function execute(): ?int
    {
        $factory = new ManagerFactory([
            'plugin' => $this->args->getOption('plugin'),
            'source' => $this->args->getOption('source'),
            'connection' => $this->args->getOption('connection'),
            'dry-run' => (bool)$this->args->getOption('dry-run'),
        ]);
        $manager = $factory->createManager($this->io);
        $config = $manager->getConfig();
        if ($config->isDryRun()) {
            $this->io->info('DRY-RUN mode enabled');
        }
        $this->io->verbose('<info>using connection</info> ' . $this->args->getOption('connection'));
        $this->io->verbose('<info>using paths</info> ' . $config->getSeedPath());

        $seeds = $manager->getSeeds();
        $adapter = $manager->getEnvironment()->getAdapter();
        // Filter seeds if --seed option is specified
        $seedOption = $this->args->getOption('seed');
        $seedsToReset = $seeds;
        if ($seedOption) {
            $requestedSeeds = array_map(trim(...), explode(',', (string)$seedOption));
            $seedsToReset = [];

            foreach ($requestedSeeds as $requestedSeed) {
                $normalizedName = $manager->normalizeSeedName($requestedSeed, $seeds);
                if ($normalizedName === null) {
                    $this->io->error(sprintf('Seed `%s` does not exist.', $requestedSeed));

                    return self::CODE_ERROR;
                }
                $seedsToReset[$normalizedName] = $seeds[$normalizedName];
            }
        }
        if ($seedsToReset === []) {
            $this->io->warning('No seeds to reset.');

            return self::CODE_SUCCESS;
        }
        // Show what will be reset and ask for confirmation
        $this->io->out('');
        $resetAllMessage = $seedOption ? '<info>The following seeds will be reset:</info>' : '<info>All seeds will be reset:</info>';
        $this->io->out($resetAllMessage);
        foreach ($seedsToReset as $seed) {
            $this->io->out('  - ' . Util::getSeedDisplayName($seed->getName()));
        }
        $this->io->out('');
        if (!$config->isDryRun()) {
            $continue = $this->io->askChoice('Do you want to continue?', ['y', 'n'], 'n');
            if ($continue !== 'y') {
                $this->io->warning('Reset operation aborted.');

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
                $this->io->info(sprintf('Reset: %s seed', $seedName));
                $count++;
            } else {
                $this->io->verbose(sprintf('Skipped (not executed): %s seed', $seedName));
            }
        }
        $this->io->out('');
        if ($config->isDryRun()) {
            $this->io->success(sprintf('DRY-RUN: Would reset %d seed(s).', $count));
        } else {
            $this->io->success(sprintf('Reset %d seed(s).', $count));
        }

        return self::CODE_SUCCESS;
    }
}
