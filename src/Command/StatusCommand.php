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
use Cake\Core\Plugin;
use Migrations\Config\ConfigInterface;
use Migrations\Db\Adapter\UnifiedMigrationsTableStorage;
use Migrations\Migration\ManagerFactory;

/**
 * Status command for built in backend
 */
class StatusCommand extends Command
{
    /**
     * Exit code for when status command is run and there are missing migrations
     *
     * @var int
     */
    public const CODE_STATUS_MISSING = 2;

    /**
     * Exit code for when status command is run and there are no missing migrations,
     * but does have down migrations
     *
     * @var int
     */
    public const CODE_STATUS_DOWN = 3;

    /**
     * The default name added to the application command list
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'migrations status';
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
            'The <info>status</info> command prints a list of all migrations, along with their current status',
            '',
            '<info>migrations status -c secondary</info>',
            '<info>migrations status -c secondary  -f json</info>',
            '<info>migrations status --all</info>',
            'Print a summary for the app and every loaded plugin that has migrations.',
            'Add <info>-v</info> to also print the per-section migration tables.',
            '<info>migrations status --cleanup</info>',
            'Remove *MISSING* migrations from the migration tracking table',
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'The plugin to run migrations for',
        ])->addOption('all', [
            'help' => 'Print a status summary for the app and every loaded plugin that has migrations. '
                . 'Use -v to also print the per-section migration tables. '
                . 'Cannot be combined with --plugin or --cleanup.',
            'boolean' => true,
            'default' => false,
        ])->addOption('connection', [
            'short' => 'c',
            'help' => 'The datasource connection to use',
            'default' => 'default',
        ])->addOption('source', [
            'short' => 's',
            'help' => 'The folder under src/Config that migrations are in',
            'default' => ConfigInterface::DEFAULT_MIGRATION_FOLDER,
        ])->addOption('format', [
            'short' => 'f',
            'help' => 'The output format: text or json. Defaults to text.',
            'choices' => ['text', 'json'],
            'default' => 'text',
        ])->addOption('cleanup', [
            'help' => 'Remove MISSING migrations from the migration tracking table',
            'boolean' => true,
            'default' => false,
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
        /** @var string|null $format */
        $format = $args->getOption('format');
        $clean = $args->getOption('cleanup');
        $all = (bool)$args->getOption('all');

        if ($all) {
            if ($args->getOption('plugin')) {
                $io->err('<error>The --all option cannot be combined with --plugin.</error>');

                return Command::CODE_ERROR;
            }
            if ($clean) {
                $io->err('<error>The --all option cannot be combined with --cleanup.</error>');

                return Command::CODE_ERROR;
            }

            return $this->executeAll($args, $io, $format);
        }

        $factory = new ManagerFactory([
            'plugin' => $args->getOption('plugin'),
            'source' => $args->getOption('source'),
            'connection' => $args->getOption('connection'),
            'dry-run' => $args->getOption('dry-run'),
        ]);
        $manager = $factory->createManager($io);

        if ($clean) {
            $removed = $manager->cleanupMissingMigrations();
            if ($removed === 0) {
                $io->out('<info>No missing migrations to clean up.</info>');
            } else {
                $io->out(sprintf('<info>Removed %d missing migration(s) from migration log.</info>', $removed));
            }

            return Command::CODE_SUCCESS;
        }

        $migrations = $manager->printStatus($format);
        $tableName = $manager->getSchemaTableName();

        switch ($format) {
            case 'json':
                $flags = 0;
                if ($args->getOption('verbose')) {
                    $flags = JSON_PRETTY_PRINT;
                }
                $migrationString = (string)json_encode($migrations, $flags);
                $io->out($migrationString);
                break;
            default:
                $this->display($migrations, $io, $tableName);
                break;
        }

        return Command::CODE_SUCCESS;
    }

    /**
     * Execute the status command for the app and every loaded plugin
     * that ships migrations.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param string|null $format Output format.
     * @return int The exit code: CODE_STATUS_MISSING (2) when there are missing entries,
     *   CODE_STATUS_DOWN (3) when there are pending down migrations, CODE_SUCCESS otherwise.
     */
    protected function executeAll(Arguments $args, ConsoleIo $io, ?string $format): int
    {
        $sections = ['app' => null];
        foreach (Plugin::loaded() as $pluginName) {
            $migrationsPath = Plugin::path($pluginName) . 'config' . DS
                . $args->getOption('source') . DS;
            if (!is_dir($migrationsPath)) {
                continue;
            }
            $sections[$pluginName] = $pluginName;
        }

        $verbose = (bool)$args->getOption('verbose');
        $jsonResults = [];
        $summary = [];
        $exitCode = Command::CODE_SUCCESS;

        foreach ($sections as $label => $plugin) {
            $factory = new ManagerFactory([
                'plugin' => $plugin,
                'source' => $args->getOption('source'),
                'connection' => $args->getOption('connection'),
                'dry-run' => $args->getOption('dry-run'),
            ]);
            $manager = $factory->createManager($io);
            $migrations = $manager->printStatus($format);

            $sectionExit = $this->statusExitCode($migrations);
            // Precedence: MISSING > DOWN > SUCCESS — once we see MISSING anywhere, keep it.
            if ($sectionExit === self::CODE_STATUS_MISSING) {
                $exitCode = self::CODE_STATUS_MISSING;
            } elseif ($sectionExit === self::CODE_STATUS_DOWN && $exitCode === Command::CODE_SUCCESS) {
                $exitCode = self::CODE_STATUS_DOWN;
            }

            $summary[$label] = $this->countActions($migrations);

            if ($format === 'json') {
                $jsonResults[$label] = $migrations;
                continue;
            }

            if (!$verbose) {
                continue;
            }

            $heading = $label === 'app' ? 'APP' : $label;
            $io->out('');
            $io->out('==================================================');
            $io->out(sprintf('<info>%s</info>', $heading));
            $io->out('==================================================');
            $this->display($migrations, $io, $manager->getSchemaTableName());
        }

        if ($format === 'json') {
            $flags = 0;
            if ($verbose) {
                $flags = JSON_PRETTY_PRINT;
            }
            $io->out((string)json_encode($jsonResults, $flags));

            return $exitCode;
        }

        $this->displaySummary($io, $summary);

        return $exitCode;
    }

    /**
     * Count actionable items (down + missing) in a section's migrations array.
     *
     * @param array $migrations The result of {@see Manager::printStatus()}.
     * @return array{down: int, missing: int}
     */
    protected function countActions(array $migrations): array
    {
        $down = 0;
        $missing = 0;
        foreach ($migrations as $migration) {
            if (!empty($migration['missing'])) {
                $missing++;
                continue;
            }
            if (($migration['status'] ?? null) === 'down') {
                $down++;
            }
        }

        return ['down' => $down, 'missing' => $missing];
    }

    /**
     * Render the trailing summary block listing sections that need action.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param array<string, array{down: int, missing: int}> $summary
     * @return void
     */
    protected function displaySummary(ConsoleIo $io, array $summary): void
    {
        $needsAction = array_filter(
            $summary,
            fn(array $counts): bool => $counts['down'] > 0 || $counts['missing'] > 0,
        );

        $io->out('');
        if (!$needsAction) {
            $io->out(sprintf(
                '<success>Summary: all %d sections are up to date.</success>',
                count($summary),
            ));

            return;
        }

        $io->out(sprintf(
            '<warning>Summary: %d of %d sections require action:</warning>',
            count($needsAction),
            count($summary),
        ));
        foreach ($needsAction as $label => $counts) {
            $heading = $label === 'app' ? 'APP' : $label;
            $parts = [];
            if ($counts['down'] > 0) {
                $parts[] = sprintf('%d pending', $counts['down']);
            }
            if ($counts['missing'] > 0) {
                $parts[] = sprintf('%d missing', $counts['missing']);
            }
            $io->out(sprintf('  - %s: %s', $heading, implode(', ', $parts)));
        }
    }

    /**
     * Compute the appropriate status exit code for a single section's migrations array.
     *
     * @param array $migrations The result of {@see Manager::printStatus()}.
     * @return int CODE_STATUS_MISSING when missing entries exist, CODE_STATUS_DOWN when
     *   any migration is pending (down), otherwise CODE_SUCCESS.
     */
    protected function statusExitCode(array $migrations): int
    {
        $hasDown = false;
        foreach ($migrations as $migration) {
            if (!empty($migration['missing'])) {
                return self::CODE_STATUS_MISSING;
            }
            if (($migration['status'] ?? null) === 'down') {
                $hasDown = true;
            }
        }

        return $hasDown ? self::CODE_STATUS_DOWN : Command::CODE_SUCCESS;
    }

    /**
     * Print migration status to stdout.
     *
     * @param \Cake\Console\ConsoleIo $io The console io
     * @param string $tableName The migration tracking table name
     * @return void
     */
    protected function display(array $migrations, ConsoleIo $io, string $tableName): void
    {
        $io->out(sprintf('using migration table <info>%s</info>', $tableName));
        if ($tableName !== UnifiedMigrationsTableStorage::TABLE_NAME) {
            $io->warning('You are using legacy phinxlog tables. Run `migrations upgrade` to switch to the unified `cake_migrations` table.');
        }
        $io->out('');

        if ($migrations) {
            $rows = [];
            $rows[] = ['Status', 'Migration ID', 'Migration Name'];

            foreach ($migrations as $migration) {
                $status = $migration['status'] === 'up' ? '<info>up</info>' : '<error>down</error>';
                $name = $migration['name'] ?
                    '<comment>' . $migration['name'] . '</comment>' :
                    '<error>** MISSING **</error>';

                $missingComment = '';
                if (!empty($migration['missing'])) {
                    $missingComment = '<error>** MISSING **</error>';
                }
                $rows[] = [$status, sprintf('%14.0f ', $migration['id']), $name . $missingComment];
            }
            $io->helper('table')->output($rows);
        } else {
            $msg = 'There are no available migrations. Try creating one using the <info>create</info> command.';
            $io->err('');
            $io->err($msg);
            $io->err('');
        }
    }
}
