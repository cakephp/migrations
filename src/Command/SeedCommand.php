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
        return 'migrations seed';
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
            'Seed the database with data',
            '',
            'Runs a seeder script that can populate the database with data, or run mutations',
            '',
            '<info>migrations seed --connection secondary --seed UserSeeder</info>',
            '',
            'The `--seed` option can be supplied multiple times to run more than one seeder',
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'The plugin to run seeders in',
        ])->addOption('connection', [
            'short' => 'c',
            'help' => 'The datasource connection to use',
            'default' => 'default',
        ])->addOption('source', [
            'short' => 's',
            'default' => ConfigInterface::DEFAULT_SEED_FOLDER,
            'help' => 'The folder where your seeders are.',
        ])->addOption('seed', [
            'help' => 'The name of the seeder that you want to run.',
            'multiple' => true,
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
     * Execute seeders based on console inputs.
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
        ]);
        $manager = $factory->createManager($io);
        $config = $manager->getConfig();
        $seeds = (array)$args->getMultipleOption('seed');

        $versionOrder = $config->getVersionOrder();
        $io->out('<info>using connection</info> ' . (string)$args->getOption('connection'));
        $io->out('<info>using paths</info> ' . $config->getMigrationPath());
        $io->out('<info>ordering by</info> ' . $versionOrder . ' time');

        $start = microtime(true);
        if (empty($seeds)) {
            // run all the seed(ers)
            $manager->seed();
        } else {
            // run seed(ers) specified in a comma-separated list of classes
            foreach ($seeds as $seed) {
                $manager->seed(trim($seed));
            }
        }
        $end = microtime(true);

        $io->out('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');

        return self::CODE_SUCCESS;
    }
}
