<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Migration;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use DateTime;
use Exception;
use InvalidArgumentException;
use Migrations\Config\ConfigInterface;
use Migrations\Shim\OutputAdapter;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\MigrationInterface;
use Phinx\Seed\AbstractSeed;
use Phinx\Seed\SeedInterface;
use Phinx\Util\Util;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class Manager
{
    public const BREAKPOINT_TOGGLE = 1;
    public const BREAKPOINT_SET = 2;
    public const BREAKPOINT_UNSET = 3;

    /**
     * @var \Migrations\Config\ConfigInterface
     */
    protected ConfigInterface $config;

    /**
     * @var \Cake\Console\ConsoleIo
     */
    protected ConsoleIo $io;

    /**
     * @var \Migrations\Migration\Environment|null
     */
    protected ?Environment $environment;

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
     * @param \Migrations\Config\ConfigInterface $config Configuration Object
     * @param \Cake\Console\ConsoleIo $io Console input/output
     */
    public function __construct(ConfigInterface $config, ConsoleIo $io)
    {
        $this->setConfig($config);
        $this->setIo($io);
    }

    /**
     * Prints the specified environment's migration status.
     *
     * @param string|null $format format to print status in (either text, json, or null)
     * @throws \RuntimeException
     * @return array array indicating if there are any missing or down migrations
     */
    public function printStatus(?string $format = null): array
    {
        $migrations = [];
        $isJson = $format === 'json';
        $defaultMigrations = $this->getMigrations();
        if (count($defaultMigrations)) {
            $env = $this->getEnvironment();
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
        $io = $this->getIo();
        $io->out(sprintf(
            '     <error>up</error>  %14.0f  %19s  %19s  <comment>%s</comment>  <error>** MISSING MIGRATION FILE **</error>',
            $version['version'],
            $version['start_time'],
            $version['end_time'],
            str_pad($version['migration_name'], $maxNameLength, ' ')
        ));

        if ($version && $version['breakpoint']) {
            $io->out('         <error>BREAKPOINT SET</error>');
        }
    }

    /**
     * Migrate to the version of the database on a given date.
     *
     * @param \DateTime $dateTime Date to migrate to
     * @param bool $fake flag that if true, we just record running the migration, but not actually do the
     *                               migration
     * @return void
     */
    public function migrateToDateTime(DateTime $dateTime, bool $fake = false): void
    {
        /** @var array<int> $versions */
        $versions = array_keys($this->getMigrations());
        $dateString = $dateTime->format('Ymdhis');
        $versionToMigrate = null;
        foreach ($versions as $version) {
            if ($dateString > $version) {
                $versionToMigrate = $version;
            }
        }

        $io = $this->getIo();
        if ($versionToMigrate === null) {
            $io->out('No migrations to run');

            return;
        }

        $io->out('Migrating to version ' . $versionToMigrate);
        $this->migrate($versionToMigrate, $fake);
    }

    /**
     * @inheritDoc
     */
    public function rollbackToDateTime(DateTime $dateTime, bool $force = false): void
    {
        $env = $this->getEnvironment();
        $versions = $env->getVersions();
        $dateString = $dateTime->format('Ymdhis');
        sort($versions);
        $versions = array_reverse($versions);

        if (empty($versions) || $dateString > $versions[0]) {
            $this->getIo()->out('No migrations to rollback');

            return;
        }

        if ($dateString < end($versions)) {
            $this->getIo()->out('Rolling back all migrations');
            $this->rollback(0);

            return;
        }

        $index = 0;
        foreach ($versions as $index => $version) {
            if ($dateString > $version) {
                break;
            }
        }

        $versionToRollback = $versions[$index];

        $this->getIo()->out('Rolling back to version ' . $versionToRollback);
        $this->rollback($versionToRollback, $force);
    }

    /**
     * Checks if the migration with version number $version as already been mark migrated
     *
     * @param int $version Version number of the migration to check
     * @return bool
     */
    public function isMigrated(int $version): bool
    {
        $adapter = $this->getEnvironment()->getAdapter();
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
        $adapter = $this->getEnvironment()->getAdapter();

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
     * @param \Cake\Console\Arguments $args Console arguments will be extracted
     *   to determine which versions to be marked as migrated
     * @return array<int> Array of versions that should be marked as migrated
     * @throws \InvalidArgumentException If the `--exclude` or `--only` options are used without `--target`
     * or version not found
     */
    public function getVersionsToMark(Arguments $args): array
    {
        $migrations = $this->getMigrations();
        $versions = array_keys($migrations);

        $versionArg = null;
        if ($args->hasArgument('version')) {
            $versionArg = $args->getArgument('version');
        }
        $targetArg = $args->getOption('target');
        $hasAllVersion = in_array($versionArg, ['all', '*'], true);
        if ((empty($versionArg) && empty($targetArg)) || $hasAllVersion) {
            return $versions;
        }

        $version = (int)$targetArg ?: (int)$versionArg;

        if ($args->getOption('only') || !empty($versionArg)) {
            if (!in_array($version, $versions)) {
                throw new InvalidArgumentException("Migration `$version` was not found !");
            }

            return [$version];
        }

        $lengthIncrease = $args->getOption('exclude') ? 0 : 1;
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
     * @return list<string> Output from the operation
     */
    public function markVersionsAsMigrated(string $path, array $versions): array
    {
        $adapter = $this->getEnvironment()->getAdapter();
        $out = [];

        if (!$versions) {
            $out[] = '<info>No migrations were found. Nothing to mark as migrated.</info>';

            return $out;
        }

        $adapter->beginTransaction();
        foreach ($versions as $version) {
            if ($this->isMigrated($version)) {
                $out[] = sprintf('<info>Skipping migration `%s` (already migrated).</info>', $version);
                continue;
            }

            try {
                $this->markMigrated($version, $path);
                $out[] = sprintf('<info>Migration `%s` successfully marked migrated !</info>', $version);
            } catch (Exception $e) {
                $adapter->rollbackTransaction();
                $out[] = sprintf(
                    '<error>An error occurred while marking migration `%s` as migrated : %s</error>',
                    $version,
                    $e->getMessage()
                );
                $out[] = '<error>All marked migrations during this process were unmarked.</error>';

                return $out;
            }
        }
        $adapter->commitTransaction();

        return $out;
    }

    /**
     * Migrate an environment to the specified version.
     *
     * @param int|null $version version to migrate to
     * @param bool $fake flag that if true, we just record running the migration, but not actually do the migration
     * @return void
     */
    public function migrate(?int $version = null, bool $fake = false): void
    {
        $migrations = $this->getMigrations();
        $env = $this->getEnvironment();
        $versions = $env->getVersions();
        $current = $env->getCurrentVersion();

        if (empty($versions) && empty($migrations)) {
            return;
        }

        if ($version === null) {
            $version = max(array_merge($versions, array_keys($migrations)));
        } else {
            if ($version != 0 && !isset($migrations[$version])) {
                $this->getIo()->out(sprintf(
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
                    $this->executeMigration($migration, MigrationInterface::DOWN, $fake);
                }
            }
        }

        ksort($migrations);
        foreach ($migrations as $migration) {
            if ($migration->getVersion() > $version) {
                break;
            }

            if (!in_array($migration->getVersion(), $versions)) {
                $this->executeMigration($migration, MigrationInterface::UP, $fake);
            }
        }
    }

    /**
     * Execute a migration against the specified environment.
     *
     * @param \Phinx\Migration\MigrationInterface $migration Migration
     * @param string $direction Direction
     * @param bool $fake flag that if true, we just record running the migration, but not actually do the migration
     * @return void
     */
    public function executeMigration(MigrationInterface $migration, string $direction = MigrationInterface::UP, bool $fake = false): void
    {
        $this->getIo()->out('');

        // Skip the migration if it should not be executed
        if (!$migration->shouldExecute()) {
            $this->printMigrationStatus($migration, 'skipped');

            return;
        }

        $this->printMigrationStatus($migration, ($direction === MigrationInterface::UP ? 'migrating' : 'reverting'));

        // Execute the migration and log the time elapsed.
        $start = microtime(true);
        $this->getEnvironment()->executeMigration($migration, $direction, $fake);
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
     * @param \Phinx\Seed\SeedInterface $seed Seed
     * @return void
     */
    public function executeSeed(SeedInterface $seed): void
    {
        $this->getIo()->out('');

        // Skip the seed if it should not be executed
        if (!$seed->shouldExecute()) {
            $this->printSeedStatus($seed, 'skipped');

            return;
        }

        $this->printSeedStatus($seed, 'seeding');

        // Execute the seeder and log the time elapsed.
        $start = microtime(true);
        $this->getEnvironment()->executeSeed($seed);
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
        $this->getIo()->out(
            ' ==' .
            ' <info>' . $name . ':</info>' .
            ' <comment>' . $status . ' ' . $duration . '</comment>',
        );
    }

    /**
     * Rollback an environment to the specified version.
     *
     * @param int|string|null $target Target
     * @param bool $force Force
     * @param bool $targetMustMatchVersion Target must match version
     * @param bool $fake Flag that if true, we just record running the migration, but not actually do the migration
     * @return void
     */
    public function rollback(int|string|null $target = null, bool $force = false, bool $targetMustMatchVersion = true, bool $fake = false): void
    {
        // note that the migrations are indexed by name (aka creation time) in ascending order
        $migrations = $this->getMigrations();

        // note that the version log are also indexed by name with the proper ascending order according to the version order
        $executedVersions = $this->getEnvironment()->getVersionLog();

        // get a list of migrations sorted in the opposite way of the executed versions
        $sortedMigrations = [];
        $io = $this->getIo();

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
                $io->out("<error>No migration found with name ($target)</error>");

                return;
            }
        }

        // Check we have at least 1 migration to revert
        $executedVersionCreationTimes = array_keys($executedVersions);
        if (empty($executedVersionCreationTimes) || $target == end($executedVersionCreationTimes)) {
            $io->out('<error>No migrations to rollback</error>');

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
            $io->out("<error>Target version ($target) not found</error>");

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
                    $io->out('<error>Breakpoint reached. Further rollbacks inhibited.</error>');
                    break;
                }
                $this->executeMigration($migration, MigrationInterface::DOWN, $fake);
                $rollbacked = true;
            }
        }

        if (!$rollbacked) {
            $this->getIo()->out('<error>No migrations to rollback</error>');
        }
    }

    /**
     * Run database seeders against an environment.
     *
     * @param string|null $seed Seeder
     * @throws \InvalidArgumentException
     * @return void
     */
    public function seed(?string $seed = null): void
    {
        $seeds = $this->getSeeds();

        if ($seed === null) {
            // run all seeders
            foreach ($seeds as $seeder) {
                if (array_key_exists($seeder->getName(), $seeds)) {
                    $this->executeSeed($seeder);
                }
            }
        } else {
            // run only one seeder
            if (array_key_exists($seed, $seeds)) {
                $this->executeSeed($seeds[$seed]);
            } else {
                throw new InvalidArgumentException(sprintf('The seed class "%s" does not exist', $seed));
            }
        }
    }

    /**
     * Gets the manager class for the given environment.
     *
     * @throws \InvalidArgumentException
     * @return \Migrations\Migration\Environment
     */
    public function getEnvironment(): Environment
    {
        if (isset($this->environment)) {
            return $this->environment;
        }

        $config = $this->getConfig();
        // create an environment instance and cache it
        $envOptions = $config->getEnvironment();
        assert(is_array($envOptions));

        $environment = new Environment('default', $envOptions);
        $environment->setIo($this->getIo());
        $this->environment = $environment;

        return $environment;
    }

    /**
     * Set the io instance
     *
     * @param \Cake\Console\ConsoleIo $io The io instance to use
     * @return $this
     */
    public function setIo(ConsoleIo $io)
    {
        $this->io = $io;

        return $this;
    }

    /**
     * Get the io instance
     *
     * @return \Cake\Console\ConsoleIo $io The io instance to use
     */
    public function getIo(): ConsoleIo
    {
        return $this->io;
    }

    /**
     * Replace the environment
     *
     * @param \Migrations\Migration\Environment $environment
     * @return $this
     */
    public function setEnvironment(Environment $environment)
    {
        $this->environment = $environment;

        return $this;
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
     * @throws \InvalidArgumentException
     * @return \Phinx\Migration\MigrationInterface[]
     */
    public function getMigrations(): array
    {
        if ($this->migrations === null) {
            $phpFiles = $this->getMigrationFiles();

            $io = $this->getIo();
            $io->verbose('Migration file');
            $io->verbose(
                array_map(
                    function ($phpFile) {
                        return "    <info>{$phpFile}</info>";
                    },
                    $phpFiles
                )
            );

            // filter the files to only get the ones that match our naming scheme
            $fileNames = [];
            /** @var \Phinx\Migration\AbstractMigration[] $versions */
            $versions = [];

            $io = $this->getIo();
            foreach ($phpFiles as $filePath) {
                if (Util::isValidMigrationFileName(basename($filePath))) {
                    $io->verbose("Valid migration file <info>{$filePath}</info>.");

                    $version = Util::getVersionFromFileName(basename($filePath));

                    if (isset($versions[$version])) {
                        throw new InvalidArgumentException(sprintf('Duplicate migration - "%s" has the same version as "%s"', $filePath, $versions[$version]->getVersion()));
                    }

                    // convert the filename to a class name
                    $class = Util::mapFileNameToClassName(basename($filePath));

                    if (isset($fileNames[$class])) {
                        throw new InvalidArgumentException(sprintf(
                            'Migration "%s" has the same name as "%s"',
                            basename($filePath),
                            $fileNames[$class]
                        ));
                    }

                    $fileNames[$class] = basename($filePath);

                    $io->verbose("Loading class <info>$class</info> from <info>$filePath</info>.");

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

                    $io->verbose("Constructing <info>$class</info>.");

                    $config = $this->getConfig();
                    $input = new ArrayInput([
                        '--plugin' => $config['plugin'] ?? null,
                        '--source' => $config['source'] ?? null,
                        '--connection' => $config->getConnection(),
                    ]);
                    $output = new OutputAdapter($io);

                    // instantiate it
                    $migration = new $class('default', $version, $input, $output);

                    if (!($migration instanceof AbstractMigration)) {
                        throw new InvalidArgumentException(sprintf(
                            'The class "%s" in file "%s" must extend \Phinx\Migration\AbstractMigration',
                            $class,
                            $filePath
                        ));
                    }

                    $versions[$version] = $migration;
                } else {
                    $io->verbose("Invalid migration file <error>{$filePath}</error>.");
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
        return Util::getFiles($this->getConfig()->getMigrationPath());
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
     * @throws \InvalidArgumentException
     * @return \Phinx\Seed\SeedInterface[]
     */
    public function getSeeds(): array
    {
        if ($this->seeds === null) {
            $phpFiles = $this->getSeedFiles();

            // filter the files to only get the ones that match our naming scheme
            $fileNames = [];
            /** @var \Phinx\Seed\SeedInterface[] $seeds */
            $seeds = [];

            $config = $this->getConfig();
            $optionDef = new InputDefinition([
                new InputOption('plugin', mode: InputOption::VALUE_OPTIONAL, default: ''),
                new InputOption('connection', mode: InputOption::VALUE_OPTIONAL, default: ''),
                new InputOption('source', mode: InputOption::VALUE_OPTIONAL, default: ''),
            ]);
            $input = new ArrayInput([
                '--plugin' => $config['plugin'] ?? null,
                '--source' => $config['source'] ?? null,
                '--connection' => $config->getConnection(),
            ], $optionDef);
            $output = new OutputAdapter($this->io);

            foreach ($phpFiles as $filePath) {
                if (Util::isValidSeedFileName(basename($filePath))) {
                    // convert the filename to a class name
                    $class = pathinfo($filePath, PATHINFO_FILENAME);
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
                    $seed->setEnvironment('default');
                    $seed->setInput($input);
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
        $this->seeds = $this->orderSeedsByDependencies((array)$this->seeds);
        if (empty($this->seeds)) {
            return [];
        }

        foreach ($this->seeds as $instance) {
            if (isset($input) && $instance instanceof AbstractSeed) {
                $instance->setInput($input);
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
        return Util::getFiles($this->getConfig()->getSeedPath());
    }

    /**
     * Sets the config.
     *
     * @param \Migrations\Config\ConfigInterface $config Configuration Object
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
     * @return \Migrations\Config\ConfigInterface
     */
    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * Toggles the breakpoint for a specific version.
     *
     * @param int|null $version Version
     * @return void
     */
    public function toggleBreakpoint(?int $version): void
    {
        $this->markBreakpoint($version, self::BREAKPOINT_TOGGLE);
    }

    /**
     * Updates the breakpoint for a specific version.
     *
     * @param int|null $version The version of the target migration
     * @param int $mark The state of the breakpoint as defined by self::BREAKPOINT_xxxx constants.
     * @return void
     */
    protected function markBreakpoint(?int $version, int $mark): void
    {
        $migrations = $this->getMigrations();
        $env = $this->getEnvironment();
        $versions = $env->getVersionLog();

        if (empty($versions) || empty($migrations)) {
            return;
        }

        if ($version === null) {
            $lastVersion = end($versions);
            $version = $lastVersion['version'];
        }

        $io = $this->getIo();
        if ($version != 0 && (!isset($versions[$version]) || !isset($migrations[$version]))) {
            $io->out(sprintf(
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

        $io->out(
            ' Breakpoint ' . ($versions[$version]['breakpoint'] ? 'set' : 'cleared') .
            ' for <info>' . $version . '</info>' .
            ' <comment>' . $migrations[$version]->getName() . '</comment>'
        );
    }

    /**
     * Remove all breakpoints
     *
     * @return void
     */
    public function removeBreakpoints(): void
    {
        $this->getIo()->out(sprintf(
            ' %d breakpoints cleared.',
            $this->getEnvironment()->getAdapter()->resetAllBreakpoints()
        ));
    }

    /**
     * Set the breakpoint for a specific version.
     *
     * @param int|null $version The version of the target migration
     * @return void
     */
    public function setBreakpoint(?int $version): void
    {
        $this->markBreakpoint($version, self::BREAKPOINT_SET);
    }

    /**
     * Unset the breakpoint for a specific version.
     *
     * @param int|null $version The version of the target migration
     * @return void
     */
    public function unsetBreakpoint(?int $version): void
    {
        $this->markBreakpoint($version, self::BREAKPOINT_UNSET);
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
