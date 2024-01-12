<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Migration;

use DateTime;
use InvalidArgumentException;
use Phinx\Config\ConfigInterface;
use Phinx\Config\NamespaceAwareInterface;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\Manager\Environment;
use Phinx\Migration\MigrationInterface;
use Phinx\Seed\AbstractSeed;
use Phinx\Seed\SeedInterface;
use Phinx\Util\Util;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Manager
{
    public const BREAKPOINT_TOGGLE = 1;
    public const BREAKPOINT_SET = 2;
    public const BREAKPOINT_UNSET = 3;

    /**
     * @var \Phinx\Config\ConfigInterface
     */
    protected ConfigInterface $config;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected InputInterface $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected OutputInterface $output;

    /**
     * @var \Phinx\Migration\Manager\Environment[]
     */
    protected array $environments = [];

    /**
     * @var \Phinx\Migration\MigrationInterface[]|null
     */
    protected ?array $migrations = null;

    /**
     * @var \Phinx\Seed\SeedInterface[]|null
     */
    protected ?array $seeds = null;

    /**
     * @var \Psr\Container\ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var int
     */
    private int $verbosityLevel = OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_NORMAL;

    /**
     * @param \Phinx\Config\ConfigInterface $config Configuration Object
     * @param \Symfony\Component\Console\Input\InputInterface $input Console Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Console Output
     */
    public function __construct(ConfigInterface $config, InputInterface $input, OutputInterface $output)
    {
        $this->setConfig($config);
        $this->setInput($input);
        $this->setOutput($output);
    }

    /**
     * Prints the specified environment's migration status.
     *
     * @param string $environment environment to print status of
     * @param string|null $format format to print status in (either text, json, or null)
     * @throws \RuntimeException
     * @return array array indicating if there are any missing or down migrations
     */
    public function printStatus(string $environment, ?string $format = null): array
    {
        $migrations = [];
        $isJson = $format === 'json';
        $defaultMigrations = $this->getMigrations('default');
        if (count($defaultMigrations)) {
            $env = $this->getEnvironment($environment);
            $versions = $env->getVersionLog();

            foreach ($defaultMigrations as $migration) {
                if (array_key_exists($migration->getVersion(), $versions)) {
                    $status = 'up';
                    unset($versions[$migration->getVersion()]);
                } else {
                    $status = 'down';
                }

                $version = $migration->getVersion();
                $migrationParams = [
                    'status' => $status,
                    'id' => $migration->getVersion(),
                    'name' => $migration->getName(),
                ];

                $migrations[$version] = $migrationParams;
            }

            foreach ($versions as $missing) {
                $version = $missing['version'];
                $migrationParams = [
                    'status' => 'up',
                    'id' => $version,
                    'name' => $missing['migration_name'],
                ];

                if (!$isJson) {
                    $migrationParams = [
                        'missing' => true,
                    ] + $migrationParams;
                }

                $migrations[$version] = $migrationParams;
            }
        }

        ksort($migrations);
        $migrations = array_values($migrations);

        return $migrations;
    }

    /**
     * Print Missing Version
     *
     * @param array $version The missing version to print (in the format returned by Environment.getVersionLog).
     * @param int $maxNameLength The maximum migration name length.
     * @return void
     */
    protected function printMissingVersion(array $version, int $maxNameLength): void
    {
        $this->getOutput()->writeln(sprintf(
            '     <error>up</error>  %14.0f  %19s  %19s  <comment>%s</comment>  <error>** MISSING MIGRATION FILE **</error>',
            $version['version'],
            $version['start_time'],
            $version['end_time'],
            str_pad($version['migration_name'], $maxNameLength, ' ')
        ));

        if ($version && $version['breakpoint']) {
            $this->getOutput()->writeln('         <error>BREAKPOINT SET</error>');
        }
    }

    /**
     * Migrate to the version of the database on a given date.
     *
     * @param string $environment Environment
     * @param \DateTime $dateTime Date to migrate to
     * @param bool $fake flag that if true, we just record running the migration, but not actually do the
     *                               migration
     * @return void
     */
    public function migrateToDateTime(string $environment, DateTime $dateTime, bool $fake = false): void
    {
        /** @var array<int> $versions */
        $versions = array_keys($this->getMigrations('default'));
        $dateString = $dateTime->format('Ymdhis');
        $versionToMigrate = null;
        foreach ($versions as $version) {
            if ($dateString > $version) {
                $versionToMigrate = $version;
            }
        }

        if ($versionToMigrate === null) {
            $this->getOutput()->writeln(
                'No migrations to run'
            );

            return;
        }

        $this->getOutput()->writeln(
            'Migrating to version ' . $versionToMigrate
        );
        $this->migrate($environment, $versionToMigrate, $fake);
    }

    /**
     * @inheritDoc
     */
    public function rollbackToDateTime(string $environment, DateTime $dateTime, bool $force = false): void
    {
        $env = $this->getEnvironment($environment);
        $versions = $env->getVersions();
        $dateString = $dateTime->format('Ymdhis');
        sort($versions);
        $versions = array_reverse($versions);

        if (empty($versions) || $dateString > $versions[0]) {
            $this->getOutput()->writeln('No migrations to rollback');

            return;
        }

        if ($dateString < end($versions)) {
            $this->getOutput()->writeln('Rolling back all migrations');
            $this->rollback($environment, 0);

            return;
        }

        $index = 0;
        foreach ($versions as $index => $version) {
            if ($dateString > $version) {
                break;
            }
        }

        $versionToRollback = $versions[$index];

        $this->getOutput()->writeln('Rolling back to version ' . $versionToRollback);
        $this->rollback($environment, $versionToRollback, $force);
    }

    /**
     * Checks if the migration with version number $version as already been mark migrated
     *
     * @param int $version Version number of the migration to check
     * @return bool
     */
    public function isMigrated(int $version): bool
    {
        $adapter = $this->getEnvironment('default')->getAdapter();
        /** @var array<int, mixed> $versions */
        $versions = array_flip($adapter->getVersions());

        return isset($versions[$version]);
    }

    /**
     * Marks migration with version number $version migrated
     *
     * @param int $version Version number of the migration to check
     * @param string $path Path where the migration file is located
     * @return bool True if success
     */
    public function markMigrated(int $version, string $path): bool
    {
        $adapter = $this->getEnvironment('default')->getAdapter();

        $migrationFile = glob($path . DS . $version . '*');

        if (empty($migrationFile)) {
            throw new RuntimeException(
                sprintf('A migration file matching version number `%s` could not be found', $version)
            );
        }

        $migrationFile = $migrationFile[0];
        /** @var class-string<\Phinx\Migration\MigrationInterface> $className */
        $className = $this->getMigrationClassName($migrationFile);
        require_once $migrationFile;
        $Migration = new $className('default', $version);

        $time = date('Y-m-d H:i:s', time());

        $adapter->migrated($Migration, 'up', $time, $time);

        return true;
    }

    /**
     * Resolves a migration class name based on $path
     *
     * @param string $path Path to the migration file of which we want the class name
     * @return string Migration class name
     */
    protected function getMigrationClassName(string $path): string
    {
        $class = (string)preg_replace('/^[0-9]+_/', '', basename($path));
        $class = str_replace('_', ' ', $class);
        $class = ucwords($class);
        $class = str_replace(' ', '', $class);
        if (strpos($class, '.') !== false) {
            /** @psalm-suppress PossiblyFalseArgument */
            $class = substr($class, 0, strpos($class, '.'));
        }

        return $class;
    }

    /**
     * Decides which versions it should mark as migrated
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input interface from which argument and options
     * will be extracted to determine which versions to be marked as migrated
     * @return array<int> Array of versions that should be marked as migrated
     * @throws \InvalidArgumentException If the `--exclude` or `--only` options are used without `--target`
     * or version not found
     */
    public function getVersionsToMark(InputInterface $input): array
    {
        $migrations = $this->getMigrations('default');
        $versions = array_keys($migrations);

        $versionArg = $input->getArgument('version');
        $targetArg = $input->getOption('target');
        $hasAllVersion = in_array($versionArg, ['all', '*'], true);
        if ((empty($versionArg) && empty($targetArg)) || $hasAllVersion) {
            return $versions;
        }

        $version = (int)$targetArg ?: (int)$versionArg;

        if ($input->getOption('only') || !empty($versionArg)) {
            if (!in_array($version, $versions)) {
                throw new InvalidArgumentException("Migration `$version` was not found !");
            }

            return [$version];
        }

        $lengthIncrease = $input->getOption('exclude') ? 0 : 1;
        $index = array_search($version, $versions);

        if ($index === false) {
            throw new InvalidArgumentException("Migration `$version` was not found !");
        }

        return array_slice($versions, 0, $index + $lengthIncrease);
    }

    /**
     * Mark all migrations in $versions array found in $path as migrated
     *
     * It will start a transaction and rollback in case one of the operation raises an exception
     *
     * @param string $path Path where to look for migrations
     * @param array<int> $versions Versions which should be marked
     * @param \Symfony\Component\Console\Output\OutputInterface $output OutputInterface used to store
     * the command output
     * @return void
     */
    public function markVersionsAsMigrated(string $path, array $versions, OutputInterface $output): void
    {
        $adapter = $this->getEnvironment('default')->getAdapter();

        if (!$versions) {
            $output->writeln('<info>No migrations were found. Nothing to mark as migrated.</info>');

            return;
        }

        $adapter->beginTransaction();
        foreach ($versions as $version) {
            if ($this->isMigrated($version)) {
                $output->writeln(sprintf('<info>Skipping migration `%s` (already migrated).</info>', $version));
                continue;
            }

            try {
                $this->markMigrated($version, $path);
                $output->writeln(
                    sprintf('<info>Migration `%s` successfully marked migrated !</info>', $version)
                );
            } catch (Exception $e) {
                $adapter->rollbackTransaction();
                $output->writeln(
                    sprintf(
                        '<error>An error occurred while marking migration `%s` as migrated : %s</error>',
                        $version,
                        $e->getMessage()
                    )
                );
                $output->writeln('<error>All marked migrations during this process were unmarked.</error>');

                return;
            }
        }
        $adapter->commitTransaction();
    }

    /**
     * Migrate an environment to the specified version.
     *
     * @param string $environment Environment
     * @param int|null $version version to migrate to
     * @param bool $fake flag that if true, we just record running the migration, but not actually do the migration
     * @return void
     */
    public function migrate(string $environment, ?int $version = null, bool $fake = false): void
    {
        $migrations = $this->getMigrations($environment);
        $env = $this->getEnvironment($environment);
        $versions = $env->getVersions();
        $current = $env->getCurrentVersion();

        if (empty($versions) && empty($migrations)) {
            return;
        }

        if ($version === null) {
            $version = max(array_merge($versions, array_keys($migrations)));
        } else {
            if ($version != 0 && !isset($migrations[$version])) {
                $this->output->writeln(sprintf(
                    '<comment>warning</comment> %s is not a valid version',
                    $version
                ));

                return;
            }
        }

        // are we migrating up or down?
        $direction = $version > $current ? MigrationInterface::UP : MigrationInterface::DOWN;

        if ($direction === MigrationInterface::DOWN) {
            // run downs first
            krsort($migrations);
            foreach ($migrations as $migration) {
                if ($migration->getVersion() <= $version) {
                    break;
                }

                if (in_array($migration->getVersion(), $versions)) {
                    $this->executeMigration($environment, $migration, MigrationInterface::DOWN, $fake);
                }
            }
        }

        ksort($migrations);
        foreach ($migrations as $migration) {
            if ($migration->getVersion() > $version) {
                break;
            }

            if (!in_array($migration->getVersion(), $versions)) {
                $this->executeMigration($environment, $migration, MigrationInterface::UP, $fake);
            }
        }
    }

    /**
     * Execute a migration against the specified environment.
     *
     * @param string $name Environment Name
     * @param \Phinx\Migration\MigrationInterface $migration Migration
     * @param string $direction Direction
     * @param bool $fake flag that if true, we just record running the migration, but not actually do the migration
     * @return void
     */
    public function executeMigration(string $name, MigrationInterface $migration, string $direction = MigrationInterface::UP, bool $fake = false): void
    {
        $this->getOutput()->writeln('', $this->verbosityLevel);

        // Skip the migration if it should not be executed
        if (!$migration->shouldExecute()) {
            $this->printMigrationStatus($migration, 'skipped');

            return;
        }

        $this->printMigrationStatus($migration, ($direction === MigrationInterface::UP ? 'migrating' : 'reverting'));

        // Execute the migration and log the time elapsed.
        $start = microtime(true);
        $this->getEnvironment($name)->executeMigration($migration, $direction, $fake);
        $end = microtime(true);

        $this->printMigrationStatus(
            $migration,
            ($direction === MigrationInterface::UP ? 'migrated' : 'reverted'),
            sprintf('%.4fs', $end - $start)
        );
    }

    /**
     * Execute a seeder against the specified environment.
     *
     * @param string $name Environment Name
     * @param \Phinx\Seed\SeedInterface $seed Seed
     * @return void
     */
    public function executeSeed(string $name, SeedInterface $seed): void
    {
        $this->getOutput()->writeln('', $this->verbosityLevel);

        // Skip the seed if it should not be executed
        if (!$seed->shouldExecute()) {
            $this->printSeedStatus($seed, 'skipped');

            return;
        }

        $this->printSeedStatus($seed, 'seeding');

        // Execute the seeder and log the time elapsed.
        $start = microtime(true);
        $this->getEnvironment($name)->executeSeed($seed);
        $end = microtime(true);

        $this->printSeedStatus(
            $seed,
            'seeded',
            sprintf('%.4fs', $end - $start)
        );
    }

    /**
     * Print Migration Status
     *
     * @param \Phinx\Migration\MigrationInterface $migration Migration
     * @param string $status Status of the migration
     * @param string|null $duration Duration the migration took the be executed
     * @return void
     */
    protected function printMigrationStatus(MigrationInterface $migration, string $status, ?string $duration = null): void
    {
        $this->printStatusOutput(
            $migration->getVersion() . ' ' . $migration->getName(),
            $status,
            $duration
        );
    }

    /**
     * Print Seed Status
     *
     * @param \Phinx\Seed\SeedInterface $seed Seed
     * @param string $status Status of the seed
     * @param string|null $duration Duration the seed took the be executed
     * @return void
     */
    protected function printSeedStatus(SeedInterface $seed, string $status, ?string $duration = null): void
    {
        $this->printStatusOutput(
            $seed->getName(),
            $status,
            $duration
        );
    }

    /**
     * Print Status in Output
     *
     * @param string $name Name of the migration or seed
     * @param string $status Status of the migration or seed
     * @param string|null $duration Duration the migration or seed took the be executed
     * @return void
     */
    protected function printStatusOutput(string $name, string $status, ?string $duration = null): void
    {
        $this->getOutput()->writeln(
            ' ==' .
            ' <info>' . $name . ':</info>' .
            ' <comment>' . $status . ' ' . $duration . '</comment>',
            $this->verbosityLevel
        );
    }

    /**
     * Rollback an environment to the specified version.
     *
     * @param string $environment Environment
     * @param int|string|null $target Target
     * @param bool $force Force
     * @param bool $targetMustMatchVersion Target must match version
     * @param bool $fake Flag that if true, we just record running the migration, but not actually do the migration
     * @return void
     */
    public function rollback(string $environment, int|string|null $target = null, bool $force = false, bool $targetMustMatchVersion = true, bool $fake = false): void
    {
        // note that the migrations are indexed by name (aka creation time) in ascending order
        $migrations = $this->getMigrations($environment);

        // note that the version log are also indexed by name with the proper ascending order according to the version order
        $executedVersions = $this->getEnvironment($environment)->getVersionLog();

        // get a list of migrations sorted in the opposite way of the executed versions
        $sortedMigrations = [];

        foreach ($executedVersions as $versionCreationTime => &$executedVersion) {
            // if we have a date (ie. the target must not match a version) and we are sorting by execution time, we
            // convert the version start time so we can compare directly with the target date
            if (!$this->getConfig()->isVersionOrderCreationTime() && !$targetMustMatchVersion) {
                /** @var \DateTime $dateTime */
                $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $executedVersion['start_time']);
                $executedVersion['start_time'] = $dateTime->format('YmdHis');
            }

            if (isset($migrations[$versionCreationTime])) {
                array_unshift($sortedMigrations, $migrations[$versionCreationTime]);
            } else {
                // this means the version is missing so we unset it so that we don't consider it when rolling back
                // migrations (or choosing the last up version as target)
                unset($executedVersions[$versionCreationTime]);
            }
        }

        if ($target === 'all' || $target === '0') {
            $target = 0;
        } elseif (!is_numeric($target) && $target !== null) { // try to find a target version based on name
            // search through the migrations using the name
            $migrationNames = array_map(function ($item) {
                return $item['migration_name'];
            }, $executedVersions);
            $found = array_search($target, $migrationNames, true);

            // check on was found
            if ($found !== false) {
                $target = (string)$found;
            } else {
                $this->getOutput()->writeln("<error>No migration found with name ($target)</error>");

                return;
            }
        }

        // Check we have at least 1 migration to revert
        $executedVersionCreationTimes = array_keys($executedVersions);
        if (empty($executedVersionCreationTimes) || $target == end($executedVersionCreationTimes)) {
            $this->getOutput()->writeln('<error>No migrations to rollback</error>');

            return;
        }

        // If no target was supplied, revert the last migration
        if ($target === null) {
            // Get the migration before the last run migration
            $prev = count($executedVersionCreationTimes) - 2;
            $target = $prev >= 0 ? $executedVersionCreationTimes[$prev] : 0;
        }

        // If the target must match a version, check the target version exists
        if ($targetMustMatchVersion && $target !== 0 && !isset($migrations[$target])) {
            $this->getOutput()->writeln("<error>Target version ($target) not found</error>");

            return;
        }

        // Rollback all versions until we find the wanted rollback target
        $rollbacked = false;

        foreach ($sortedMigrations as $migration) {
            if ($targetMustMatchVersion && $migration->getVersion() == $target) {
                break;
            }

            if (in_array($migration->getVersion(), $executedVersionCreationTimes)) {
                $executedVersion = $executedVersions[$migration->getVersion()];

                if (!$targetMustMatchVersion) {
                    if (
                        ($this->getConfig()->isVersionOrderCreationTime() && $executedVersion['version'] <= $target) ||
                        (!$this->getConfig()->isVersionOrderCreationTime() && $executedVersion['start_time'] <= $target)
                    ) {
                        break;
                    }
                }

                if ($executedVersion['breakpoint'] != 0 && !$force) {
                    $this->getOutput()->writeln('<error>Breakpoint reached. Further rollbacks inhibited.</error>');
                    break;
                }
                $this->executeMigration($environment, $migration, MigrationInterface::DOWN, $fake);
                $rollbacked = true;
            }
        }

        if (!$rollbacked) {
            $this->getOutput()->writeln('<error>No migrations to rollback</error>');
        }
    }

    /**
     * Run database seeders against an environment.
     *
     * @param string $environment Environment
     * @param string|null $seed Seeder
     * @throws \InvalidArgumentException
     * @return void
     */
    public function seed(string $environment, ?string $seed = null): void
    {
        $seeds = $this->getSeeds($environment);

        if ($seed === null) {
            // run all seeders
            foreach ($seeds as $seeder) {
                if (array_key_exists($seeder->getName(), $seeds)) {
                    $this->executeSeed($environment, $seeder);
                }
            }
        } else {
            // run only one seeder
            if (array_key_exists($seed, $seeds)) {
                $this->executeSeed($environment, $seeds[$seed]);
            } else {
                throw new InvalidArgumentException(sprintf('The seed class "%s" does not exist', $seed));
            }
        }
    }

    /**
     * Sets the environments.
     *
     * @param \Phinx\Migration\Manager\Environment[] $environments Environments
     * @return $this
     */
    public function setEnvironments(array $environments = [])
    {
        $this->environments = $environments;

        return $this;
    }

    /**
     * Gets the manager class for the given environment.
     *
     * @param string $name Environment Name
     * @throws \InvalidArgumentException
     * @return \Phinx\Migration\Manager\Environment
     */
    public function getEnvironment(string $name): Environment
    {
        if (isset($this->environments[$name])) {
            return $this->environments[$name];
        }

        // check the environment exists
        if (!$this->getConfig()->hasEnvironment($name)) {
            throw new InvalidArgumentException(sprintf(
                'The environment "%s" does not exist',
                $name
            ));
        }

        // create an environment instance and cache it
        $envOptions = $this->getConfig()->getEnvironment($name);
        $envOptions['version_order'] = $this->getConfig()->getVersionOrder();
        $envOptions['data_domain'] = $this->getConfig()->getDataDomain();

        $environment = new Environment($name, $envOptions);
        $this->environments[$name] = $environment;
        $environment->setInput($this->getInput());
        $environment->setOutput($this->getOutput());

        return $environment;
    }

    /**
     * Sets the user defined PSR-11 container
     *
     * @param \Psr\Container\ContainerInterface $container Container
     * @return $this
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Sets the console input.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input
     * @return $this
     */
    public function setInput(InputInterface $input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Gets the console input.
     *
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    public function getInput(): InputInterface
    {
        return $this->input;
    }

    /**
     * Sets the console output.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     * @return $this
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Gets the console output.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * Sets the database migrations.
     *
     * @param \Phinx\Migration\AbstractMigration[] $migrations Migrations
     * @return $this
     */
    public function setMigrations(array $migrations)
    {
        $this->migrations = $migrations;

        return $this;
    }

    /**
     * Gets an array of the database migrations, indexed by migration name (aka creation time) and sorted in ascending
     * order
     *
     * @param string $environment Environment
     * @throws \InvalidArgumentException
     * @return \Phinx\Migration\MigrationInterface[]
     */
    public function getMigrations(string $environment): array
    {
        if ($this->migrations === null) {
            $phpFiles = $this->getMigrationFiles();

            if ($this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                $this->getOutput()->writeln('Migration file');
                $this->getOutput()->writeln(
                    array_map(
                        function ($phpFile) {
                            return "    <info>{$phpFile}</info>";
                        },
                        $phpFiles
                    )
                );
            }

            // filter the files to only get the ones that match our naming scheme
            $fileNames = [];
            /** @var \Phinx\Migration\AbstractMigration[] $versions */
            $versions = [];

            foreach ($phpFiles as $filePath) {
                if (Util::isValidMigrationFileName(basename($filePath))) {
                    if ($this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                        $this->getOutput()->writeln("Valid migration file <info>{$filePath}</info>.");
                    }

                    $version = Util::getVersionFromFileName(basename($filePath));

                    if (isset($versions[$version])) {
                        throw new InvalidArgumentException(sprintf('Duplicate migration - "%s" has the same version as "%s"', $filePath, $versions[$version]->getVersion()));
                    }

                    $config = $this->getConfig();
                    $namespace = $config instanceof NamespaceAwareInterface ? $config->getMigrationNamespaceByPath(dirname($filePath)) : null;

                    // convert the filename to a class name
                    $class = ($namespace === null ? '' : $namespace . '\\') . Util::mapFileNameToClassName(basename($filePath));

                    if (isset($fileNames[$class])) {
                        throw new InvalidArgumentException(sprintf(
                            'Migration "%s" has the same name as "%s"',
                            basename($filePath),
                            $fileNames[$class]
                        ));
                    }

                    $fileNames[$class] = basename($filePath);

                    if ($this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                        $this->getOutput()->writeln("Loading class <info>$class</info> from <info>$filePath</info>.");
                    }

                    // load the migration file
                    $orig_display_errors_setting = ini_get('display_errors');
                    ini_set('display_errors', 'On');
                    /** @noinspection PhpIncludeInspection */
                    require_once $filePath;
                    ini_set('display_errors', $orig_display_errors_setting);
                    if (!class_exists($class)) {
                        throw new InvalidArgumentException(sprintf(
                            'Could not find class "%s" in file "%s"',
                            $class,
                            $filePath
                        ));
                    }

                    if ($this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                        $this->getOutput()->writeln("Running <info>$class</info>.");
                    }

                    // instantiate it
                    $migration = new $class($environment, $version, $this->getInput(), $this->getOutput());

                    if (!($migration instanceof AbstractMigration)) {
                        throw new InvalidArgumentException(sprintf(
                            'The class "%s" in file "%s" must extend \Phinx\Migration\AbstractMigration',
                            $class,
                            $filePath
                        ));
                    }

                    $versions[$version] = $migration;
                } else {
                    if ($this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                        $this->getOutput()->writeln("Invalid migration file <error>{$filePath}</error>.");
                    }
                }
            }

            ksort($versions);
            $this->setMigrations($versions);
        }

        return (array)$this->migrations;
    }

    /**
     * Returns a list of migration files found in the provided migration paths.
     *
     * @return string[]
     */
    protected function getMigrationFiles(): array
    {
        return Util::getFiles($this->getConfig()->getMigrationPaths());
    }

    /**
     * Sets the database seeders.
     *
     * @param \Phinx\Seed\SeedInterface[] $seeds Seeders
     * @return $this
     */
    public function setSeeds(array $seeds)
    {
        $this->seeds = $seeds;

        return $this;
    }

    /**
     * Get seed dependencies instances from seed dependency array
     *
     * @param \Phinx\Seed\SeedInterface $seed Seed
     * @return \Phinx\Seed\SeedInterface[]
     */
    protected function getSeedDependenciesInstances(SeedInterface $seed): array
    {
        $dependenciesInstances = [];
        $dependencies = $seed->getDependencies();
        if (!empty($dependencies) && !empty($this->seeds)) {
            foreach ($dependencies as $dependency) {
                foreach ($this->seeds as $seed) {
                    if (get_class($seed) === $dependency) {
                        $dependenciesInstances[get_class($seed)] = $seed;
                    }
                }
            }
        }

        return $dependenciesInstances;
    }

    /**
     * Order seeds by dependencies
     *
     * @param \Phinx\Seed\SeedInterface[] $seeds Seeds
     * @return \Phinx\Seed\SeedInterface[]
     */
    protected function orderSeedsByDependencies(array $seeds): array
    {
        $orderedSeeds = [];
        foreach ($seeds as $seed) {
            $orderedSeeds[get_class($seed)] = $seed;
            $dependencies = $this->getSeedDependenciesInstances($seed);
            if (!empty($dependencies)) {
                $orderedSeeds = array_merge($this->orderSeedsByDependencies($dependencies), $orderedSeeds);
            }
        }

        return $orderedSeeds;
    }

    /**
     * Gets an array of database seeders.
     *
     * @param string $environment Environment
     * @throws \InvalidArgumentException
     * @return \Phinx\Seed\SeedInterface[]
     */
    public function getSeeds(string $environment): array
    {
        if ($this->seeds === null) {
            $phpFiles = $this->getSeedFiles();

            // filter the files to only get the ones that match our naming scheme
            $fileNames = [];
            /** @var \Phinx\Seed\SeedInterface[] $seeds */
            $seeds = [];

            foreach ($phpFiles as $filePath) {
                if (Util::isValidSeedFileName(basename($filePath))) {
                    $config = $this->getConfig();
                    $namespace = $config instanceof NamespaceAwareInterface ? $config->getSeedNamespaceByPath(dirname($filePath)) : null;

                    // convert the filename to a class name
                    $class = ($namespace === null ? '' : $namespace . '\\') . pathinfo($filePath, PATHINFO_FILENAME);
                    $fileNames[$class] = basename($filePath);

                    // load the seed file
                    /** @noinspection PhpIncludeInspection */
                    require_once $filePath;
                    if (!class_exists($class)) {
                        throw new InvalidArgumentException(sprintf(
                            'Could not find class "%s" in file "%s"',
                            $class,
                            $filePath
                        ));
                    }

                    // instantiate it
                    /** @var \Phinx\Seed\AbstractSeed $seed */
                    if (isset($this->container)) {
                        $seed = $this->container->get($class);
                    } else {
                        $seed = new $class();
                    }
                    $seed->setEnvironment($environment);
                    $input = $this->getInput();
                    $seed->setInput($input);

                    $output = $this->getOutput();
                    $seed->setOutput($output);

                    if (!($seed instanceof AbstractSeed)) {
                        throw new InvalidArgumentException(sprintf(
                            'The class "%s" in file "%s" must extend \Phinx\Seed\AbstractSeed',
                            $class,
                            $filePath
                        ));
                    }

                    $seeds[$class] = $seed;
                }
            }

            ksort($seeds);
            $this->setSeeds($seeds);
        }

        assert(!empty($this->seeds), 'seeds must be set');
        $this->seeds = $this->orderSeedsByDependencies($this->seeds);

        if (empty($this->seeds)) {
            return [];
        }

        foreach ($this->seeds as $instance) {
            if ($instance instanceof AbstractSeed) {
                $instance->setInput($this->input);
            }
        }

        return $this->seeds;
    }

    /**
     * Returns a list of seed files found in the provided seed paths.
     *
     * @return string[]
     */
    protected function getSeedFiles(): array
    {
        return Util::getFiles($this->getConfig()->getSeedPaths());
    }

    /**
     * Sets the config.
     *
     * @param \Phinx\Config\ConfigInterface $config Configuration Object
     * @return $this
     */
    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Gets the config.
     *
     * @return \Phinx\Config\ConfigInterface
     */
    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * Toggles the breakpoint for a specific version.
     *
     * @param string $environment Environment name
     * @param int|null $version Version
     * @return void
     */
    public function toggleBreakpoint(string $environment, ?int $version): void
    {
        $this->markBreakpoint($environment, $version, self::BREAKPOINT_TOGGLE);
    }

    /**
     * Updates the breakpoint for a specific version.
     *
     * @param string $environment The required environment
     * @param int|null $version The version of the target migration
     * @param int $mark The state of the breakpoint as defined by self::BREAKPOINT_xxxx constants.
     * @return void
     */
    protected function markBreakpoint(string $environment, ?int $version, int $mark): void
    {
        $migrations = $this->getMigrations($environment);
        $env = $this->getEnvironment($environment);
        $versions = $env->getVersionLog();

        if (empty($versions) || empty($migrations)) {
            return;
        }

        if ($version === null) {
            $lastVersion = end($versions);
            $version = $lastVersion['version'];
        }

        if ($version != 0 && (!isset($versions[$version]) || !isset($migrations[$version]))) {
            $this->output->writeln(sprintf(
                '<comment>warning</comment> %s is not a valid version',
                $version
            ));

            return;
        }

        switch ($mark) {
            case self::BREAKPOINT_TOGGLE:
                $env->getAdapter()->toggleBreakpoint($migrations[$version]);
                break;
            case self::BREAKPOINT_SET:
                if ($versions[$version]['breakpoint'] == 0) {
                    $env->getAdapter()->setBreakpoint($migrations[$version]);
                }
                break;
            case self::BREAKPOINT_UNSET:
                if ($versions[$version]['breakpoint'] == 1) {
                    $env->getAdapter()->unsetBreakpoint($migrations[$version]);
                }
                break;
        }

        $versions = $env->getVersionLog();

        $this->getOutput()->writeln(
            ' Breakpoint ' . ($versions[$version]['breakpoint'] ? 'set' : 'cleared') .
            ' for <info>' . $version . '</info>' .
            ' <comment>' . $migrations[$version]->getName() . '</comment>'
        );
    }

    /**
     * Remove all breakpoints
     *
     * @param string $environment The required environment
     * @return void
     */
    public function removeBreakpoints(string $environment): void
    {
        $this->getOutput()->writeln(sprintf(
            ' %d breakpoints cleared.',
            $this->getEnvironment($environment)->getAdapter()->resetAllBreakpoints()
        ));
    }

    /**
     * Set the breakpoint for a specific version.
     *
     * @param string $environment The required environment
     * @param int|null $version The version of the target migration
     * @return void
     */
    public function setBreakpoint(string $environment, ?int $version): void
    {
        $this->markBreakpoint($environment, $version, self::BREAKPOINT_SET);
    }

    /**
     * Unset the breakpoint for a specific version.
     *
     * @param string $environment The required environment
     * @param int|null $version The version of the target migration
     * @return void
     */
    public function unsetBreakpoint(string $environment, ?int $version): void
    {
        $this->markBreakpoint($environment, $version, self::BREAKPOINT_UNSET);
    }

    /**
     * @param int $verbosityLevel Verbosity level for info messages
     * @return $this
     */
    public function setVerbosityLevel(int $verbosityLevel)
    {
        $this->verbosityLevel = $verbosityLevel;

        return $this;
    }

    /**
     * Reset the migrations stored in the object
     *
     * @return void
     */
    public function resetMigrations(): void
    {
        $this->migrations = null;
    }

    /**
     * Reset the seeds stored in the object
     *
     * @return void
     */
    public function resetSeeds(): void
    {
        $this->seeds = null;
    }
}
