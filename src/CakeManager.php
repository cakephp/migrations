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
namespace Migrations;

use DateTime;
use Exception;
use InvalidArgumentException;
use Phinx\Migration\Manager;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Overrides Phinx Manager class in order to provide an interface
 * for running migrations within an app
 */
class CakeManager extends Manager
{
    /**
     * @var int
     */
    public int $maxNameLength = 0;

    /**
     * Instance of InputInterface the Manager is dealing with for the current shell call
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected InputInterface $input;

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

    /**
     * Prints the specified environment's migration status.
     *
     * @param string $environment Environment name.
     * @param null|string $format Format (`json` or `array`).
     * @return array Array of migrations.
     */
    public function printStatus(string $environment, ?string $format = null): array
    {
        $migrations = [];
        $isJson = $format === 'json';
        $defaultMigrations = $this->getMigrations('default');
        if ($defaultMigrations) {
            $env = $this->getEnvironment($environment);
            $versions = $env->getVersionLog();
            $this->maxNameLength = $versions ? max(array_map(function ($version) {
                return strlen((string)$version['migration_name']);
            }, $versions)) : 0;

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
     * @param string $environment Environment
     * @param \DateTime $dateTime Date to migrate to
     * @param bool $fake flag that if true, we just record running the migration, but not actually do the
     *   migration
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
                'No migrations to run',
            );

            return;
        }

        $this->getOutput()->writeln(
            'Migrating to version ' . $versionToMigrate,
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

        if (!$versions || $dateString > $versions[0]) {
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

        if (!$migrationFile) {
            throw new RuntimeException(
                sprintf('A migration file matching version number `%s` could not be found', $version),
            );
        }

        $migrationFile = $migrationFile[0];
        /** @var class-string<\Phinx\Migration\MigrationInterface> $className */
        $className = $this->getMigrationClassName($migrationFile);
        require_once $migrationFile;

        // BaseMigration uses different constructor signature than Phinx AbstractMigration
        if (is_subclass_of($className, BaseMigration::class)) {
            $Migration = new $className($version);
        } else {
            $Migration = new $className('default', $version);
        }

        $time = date('Y-m-d H:i:s', time());

        $adapter->migrated($Migration, 'up', $time, $time);

        return true;
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
        if ((!$versionArg && !$targetArg) || $hasAllVersion) {
            return $versions;
        }

        $version = (int)$targetArg ?: (int)$versionArg;

        if ($input->getOption('only') || $versionArg) {
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
                    sprintf('<info>Migration `%s` successfully marked migrated !</info>', $version),
                );
            } catch (Exception $e) {
                $adapter->rollbackTransaction();
                $output->writeln(
                    sprintf(
                        '<error>An error occurred while marking migration `%s` as migrated : %s</error>',
                        $version,
                        $e->getMessage(),
                    ),
                );
                $output->writeln('<error>All marked migrations during this process were unmarked.</error>');

                return;
            }
        }
        $adapter->commitTransaction();
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
            $class = substr($class, 0, strpos($class, '.'));
        }

        return $class;
    }

    /**
     * Sets the InputInterface the Manager is dealing with for the current shell call
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Instance of InputInterface
     * @return $this
     */
    public function setInput(InputInterface $input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Gets an array of the database migrations, indexed by migration name (aka creation time) and sorted in ascending
     * order
     *
     * Overrides parent to handle both Phinx AbstractMigration and CakePHP BaseMigration constructors
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
                        $phpFiles,
                    ),
                );
            }

            // filter the files to only get the ones that match our naming scheme
            $fileNames = [];
            /** @var \Phinx\Migration\MigrationInterface[] $versions */
            $versions = [];

            foreach ($phpFiles as $filePath) {
                if (\Phinx\Util\Util::isValidMigrationFileName(basename($filePath))) {
                    if ($this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                        $this->getOutput()->writeln("Valid migration file <info>{$filePath}</info>.");
                    }

                    $version = \Phinx\Util\Util::getVersionFromFileName(basename($filePath));

                    if (isset($versions[$version])) {
                        throw new InvalidArgumentException(sprintf('Duplicate migration - "%s" has the same version as "%s"', $filePath, $versions[$version]->getVersion()));
                    }

                    $config = $this->getConfig();
                    $namespace = $config instanceof \Phinx\Config\NamespaceAwareInterface ? $config->getMigrationNamespaceByPath(dirname($filePath)) : null;

                    // convert the filename to a class name
                    $class = ($namespace === null ? '' : $namespace . '\\') . \Phinx\Util\Util::mapFileNameToClassName(basename($filePath));

                    if (isset($fileNames[$class])) {
                        throw new InvalidArgumentException(sprintf(
                            'Migration "%s" has the same name as "%s"',
                            basename($filePath),
                            $fileNames[$class],
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
                            $filePath,
                        ));
                    }

                    if ($this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                        $this->getOutput()->writeln("Running <info>$class</info>.");
                    }

                    // instantiate it - BaseMigration uses different constructor than Phinx AbstractMigration
                    if (is_subclass_of($class, BaseMigration::class)) {
                        $migration = new $class($version);
                    } else {
                        $migration = new $class($environment, $version, $this->getInput(), $this->getOutput());
                    }

                    if (!($migration instanceof \Phinx\Migration\MigrationInterface)) {
                        throw new InvalidArgumentException(sprintf(
                            'The class "%s" in file "%s" must implement \Phinx\Migration\MigrationInterface',
                            $class,
                            $filePath,
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
            /** @phpstan-ignore-next-line argument.type */
            $this->setMigrations($versions);
        }

        return $this->migrations;
    }

    /**
     * Gets an array of database seeders.
     *
     * Overload the basic behavior to add an instance of the InputInterface the shell call is
     * using in order to give the ability to the AbstractSeed::call() method to propagate options
     * to the other MigrationsDispatcher it is generating.
     *
     * @throws \InvalidArgumentException
     * @param string $environment Environment.
     * @return \Phinx\Seed\SeedInterface[]
     */
    public function getSeeds(string $environment): array
    {
        parent::getSeeds($environment);
        if (!$this->seeds) {
            return [];
        }

        foreach ($this->seeds as $instance) {
            if ($instance instanceof AbstractSeed) {
                $instance->setInput($this->input);
            }
        }

        return $this->seeds;
    }
}
