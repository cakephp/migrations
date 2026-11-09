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
 * Seed status command shows which seeds have been executed
 */
class SeedStatusCommand extends Command
{
    /**
     * The default name added to the application command list
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'seeds status';
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
            'The <info>status</info> command prints a list of all seeds, along with their execution status',
            '',
            '<info>seeds status</info>',
            '<info>seeds status --plugin Demo</info>',
            '<info>seeds status -c secondary</info>',
            '<info>seeds status -f json</info>',
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'The plugin to check seed status for',
        ])->addOption('connection', [
            'short' => 'c',
            'help' => 'The datasource connection to use',
            'default' => 'default',
        ])->addOption('source', [
            'short' => 's',
            'help' => 'The folder under config that seeds are in',
            'default' => ConfigInterface::DEFAULT_SEED_FOLDER,
        ])->addOption('format', [
            'short' => 'f',
            'help' => 'The output format: text or json. Defaults to text.',
            'choices' => ['text', 'json'],
            'default' => 'text',
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
        ]);
        $manager = $factory->createManager($this->io);
        $config = $manager->getConfig();
        $this->io->verbose('<info>using connection</info> ' . $this->args->getOption('connection'));
        $this->io->verbose('<info>using paths</info> ' . $config->getSeedPath());

        $seeds = $manager->getSeeds();
        $adapter = $manager->getEnvironment()->getAdapter();
        // Ensure seed schema table exists
        if (!$adapter->hasTable($adapter->getSeedSchemaTableName())) {
            $adapter->createSeedSchemaTable();
        }
        $seedLog = $adapter->getSeedLog();
        // Build status list
        $statuses = [];
        foreach ($seeds as $seed) {
            $plugin = Util::getSeedPlugin($seed);
            $seedName = $seed->getName();
            $executed = false;
            $executedAt = null;

            foreach ($seedLog as $entry) {
                if ($entry['seed_name'] === $seedName && Util::matchesSeedPlugin($entry['plugin'], $plugin)) {
                    $executed = true;
                    $executedAt = $entry['executed_at'];
                    break;
                }
            }

            // Strip 'Seed' suffix for display and add ' seed' suffix
            $displayName = Util::getSeedDisplayName($seedName) . ' seed';

            $statuses[] = [
                'seedName' => $displayName,
                'plugin' => $plugin,
                'status' => $executed ? 'executed' : 'pending',
                'executedAt' => $executedAt,
                'idempotent' => $seed->isIdempotent(),
            ];
        }
        $format = (string)$this->args->getOption('format');
        if ($format === 'json') {
            $json = json_encode($statuses, JSON_PRETTY_PRINT);
            if ($json !== false) {
                $this->io->out($json);
            }

            return self::CODE_SUCCESS;
        }
        // Text format
        if (!$statuses) {
            $this->io->warning('No seeds found.');

            return self::CODE_SUCCESS;
        }
        $this->io->out('');
        $this->io->out('<info>Current seed execution status:</info>');
        $this->io->out('');

        $maxNameLength = max(array_map(fn(array $s): int => strlen($s['seedName']), $statuses));
        $maxPluginLength = max(array_map(fn(array $s): int => strlen($s['plugin'] ?? ''), $statuses));
        foreach ($statuses as $status) {
            $seedName = str_pad($status['seedName'], $maxNameLength);
            $plugin = $status['plugin'] ? str_pad($status['plugin'], $maxPluginLength) : str_repeat(' ', $maxPluginLength);
            $idempotent = $status['idempotent'] ? ' <info>(idempotent)</info>' : '';

            if ($status['status'] === 'executed') {
                $statusText = '<info>executed</info>';
                $date = $status['executedAt'] ? ' (' . $status['executedAt'] . ')' : '';
                $this->io->out(sprintf('  %s %s  %s%s%s', $statusText, $plugin, $seedName, $date, $idempotent));
            } else {
                $statusText = '<comment>pending</comment> ';
                $this->io->out(sprintf('  %s %s  %s%s', $statusText, $plugin, $seedName, $idempotent));
            }
        }
        $this->io->out('');

        return self::CODE_SUCCESS;
    }
}
