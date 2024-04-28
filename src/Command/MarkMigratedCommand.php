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
use InvalidArgumentException;
use Migrations\Config\ConfigInterface;
use Migrations\Migration\ManagerFactory;

/**
 * MarkMigrated command marks migrations as run when they haven't been.
 */
class MarkMigratedCommand extends Command
{
    /**
     * The default name added to the application command list
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'migrations mark_migrated';
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
            'Mark a migration as applied',
            '',
            'Can mark one or more migrations as applied without applying the changes in the migration.',
            '',
            '<info>migrations mark_migrated --connection secondary</info>',
            'Mark all migrations as applied',
            '',
            '<info>migrations mark_migrated --connection secondary --target 003</info>',
            'mark migrations as applied up to the 003',
            '',
            '<info>migrations mark_migrated --target 003 --only</info>',
            'mark only 003 as applied.',
            '',
            '<info>migrations mark_migrated --target 003 --exclude</info>',
            'mark up to 003, but not 003 as applied.',
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'The plugin to mark migrations for',
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
            'help' => 'Migrations from the beginning to the provided version will be marked as applied.',
        ])->addOption('only', [
            'short' => 'o',
            'help' => 'If present, only the target migration will be marked as applied.',
            'boolean' => true,
        ])->addOption('exclude', [
            'short' => 'x',
            'help' => 'If present, migrations from the beginning until the target version but not including the target will be marked as applied.',
            'boolean' => true,
        ]);

        return $parser;
    }

    /**
     * Checks for an invalid use of `--exclude` or `--only`
     *
     * @param \Cake\Console\Arguments $args The console arguments
     * @return bool Returns true when it is an invalid use of `--exclude` or `--only` otherwise false
     */
    protected function invalidOnlyOrExclude(Arguments $args): bool
    {
        return ($args->getOption('exclude') && $args->getOption('only')) ||
            ($args->getOption('exclude') || $args->getOption('only')) &&
            $args->getOption('target') === null;
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
        ]);
        $manager = $factory->createManager($io);
        $config = $manager->getConfig();
        $path = $config->getMigrationPath();

        if ($this->invalidOnlyOrExclude($args)) {
            $io->err(
                '<error>You should use `--exclude` OR `--only` (not both) along with a `--target` !</error>'
            );

            return self::CODE_ERROR;
        }

        try {
            $versions = $manager->getVersionsToMark($args);
        } catch (InvalidArgumentException $e) {
            $io->err(sprintf('<error>%s</error>', $e->getMessage()));

            return self::CODE_ERROR;
        }

        $output = $manager->markVersionsAsMigrated($path, $versions);
        array_map(fn ($line) => $io->out($line), $output);

        return self::CODE_SUCCESS;
    }
}
