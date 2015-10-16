<?php
/**
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Command;

use InvalidArgumentException;
use Migrations\ConfigurationTrait;
use Phinx\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class MarkMigrated extends AbstractCommand
{

    use ConfigurationTrait;

    /**
     * The console output instance
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return mixed
     */
    public function output(OutputInterface $output = null)
    {
        if ($output !== null) {
            $this->output = $output;
        }
        return $this->output;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mark_migrated')
            ->setDescription('Mark a migration as migrated')
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'DEPRECATED: use `bin/cake migrations mark_migrated --target=VERSION --only` instead'
            )
            ->setHelp(sprintf(
                '%sMark migrations as migrated%s',
                PHP_EOL,
                PHP_EOL
            ))
            ->addOption('plugin', 'p', InputOption::VALUE_REQUIRED, 'The plugin the file should be created for')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'The datasource connection to use')
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'The folder where migrations are in')
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED,
                'It will mark migrations from beginning to the given version'
            )
            ->addOption(
                'exclude',
                'x',
                InputOption::VALUE_NONE,
                'If present it will mark migrations from beginning until the given version, excluding it'
            )
            ->addOption(
                'only',
                'o',
                InputOption::VALUE_NONE,
                'If present it will only mark the given migration version'
            );
    }

    /**
     * Mark migrations as migrated
     *
     * `bin/cake migrations mark_migrated` mark every migrations as migrated
     * `bin/cake migrations mark_migrated all` DEPRECATED: the same effect as above
     * `bin/cake migrations mark_migrated --target=VERSION` mark migrations as migrated up to the VERSION param
     * `bin/cake migrations mark_migrated --target=20150417223600 --exclude` mark migrations as migrated up to
     *  and except the VERSION param
     * `bin/cake migrations mark_migrated --target=20150417223600 --only` mark only the VERSION migration as migrated
     * `bin/cake migrations mark_migrated 20150417223600` DEPRECATED: the same effect as above
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input the input object
     * @param \Symfony\Component\Console\Output\OutputInterface $output the output object
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setInput($input);
        $this->bootstrap($input, $output);
        $this->output($output);

        $path = $this->getConfig()->getMigrationPath();

        if ($this->invalidOnlyOrExclude()) {
            $output->writeln(
                "<error>You should use `--exclude` OR `--only` (not both) along with a `--target` !</error>"
            );
            return;
        }

        if ($this->isUsingDeprecatedAll()) {
            $this->outputDeprecatedAllMessage();
        }

        if ($this->isUsingDeprecatedVersion()) {
            $this->outputDeprecatedVersionMessage();
        }

        try {
            $versions = $this->getVersionsToMark($input);
        } catch (InvalidArgumentException $e) {
            $output->writeln(sprintf("<error>%s</error>", $e->getMessage()));
            return;
        }

        $this->markVersionsAsMigrated($path, $versions);
    }

    /**
     * Mark all migrations in $versions array found in $path as migrated
     *
     * It will start a transaction and rollback in case one of the operation raises an exception
     *
     * @param string $path Path where to look for migrations
     * @param array $versions Versions which should be marked
     * @return void
     */
    protected function markVersionsAsMigrated($path, $versions)
    {
        $manager = $this->getManager();
        $adapter = $manager->getEnvironment('default')->getAdapter();
        $output = $this->output();

        if (empty($versions)) {
            $output->writeln('<info>No migrations were found. Nothing to mark as migrated.</info>');
            return;
        }

        $adapter->beginTransaction();
        foreach ($versions as $version) {
            if ($manager->isMigrated($version)) {
                $output->writeln(sprintf('<info>Skipping migration `%s` (already migrated).</info>', $version));
            } else {
                try {
                    $this->getManager()->markMigrated($version, $path);
                    $output->writeln(
                        sprintf('<info>Migration `%s` successfully marked migrated !</info>', $version)
                    );
                } catch (\Exception $e) {
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
        }
        $adapter->commitTransaction();
    }

    /**
     * Decides which versions it should mark as migrated
     *
     * @return array Array of versions that should be marked as migrated
     * @throws \InvalidArgumentException If the `--exclude` or `--only` options are used without `--target`
     * or version not found
     */
    protected function getVersionsToMark()
    {
        $migrations = $this->getManager()->getMigrations();
        $versions = array_keys($migrations);

        if ($this->isAllVersion()) {
            return $versions;
        }

        $version = $this->getTarget();

        if ($this->isOnly()) {
            if (!in_array($version, $versions)) {
                throw new InvalidArgumentException("Migration `$version` was not found !");
            }

            return [$version];
        }

        $lengthIncrease = $this->hasExclude() ? 0 : 1;
        $index = array_search($version, $versions);

        if ($index === false) {
            throw new \InvalidArgumentException("Migration `$version` was not found !");
        }

        return array_slice($versions, 0, $index + $lengthIncrease);
    }

    /**
     * Returns the target version from `--target` or from the deprecated version argument
     *
     * @return string Version found as target
     */
    protected function getTarget()
    {
        $target = $this->input->getOption('target');
        return $target ? $target : $this->input->getArgument('version');
    }

    /**
     * Checks if the $version is for all migrations
     *
     * @return bool Returns true if it should try to mark all versions
     */
    protected function isAllVersion()
    {
        if ($this->isUsingDeprecatedVersion()) {
            return false;
        }

        return $this->isUsingDeprecatedAll() || $this->input->getOption('target') === null;
    }

    /**
     * Checks if the version is using the deprecated `all`
     *
     * @return bool Returns true if it is using the deprecated `all` otherwise false
     */
    protected function isUsingDeprecatedAll()
    {
        $version = $this->input->getArgument('version');
        return $version === 'all' || $version === '*';
    }

    /**
     * Checks if the input has the `--only` option or it is using the deprecated version argument
     *
     * @return bool Returns true when it is trying to mark only one migration
     */
    protected function isOnly()
    {

        return $this->hasOnly() || $this->isUsingDeprecatedVersion();
    }

    /**
     * Checks if the input has the `--exclude` option
     *
     * @return bool Returns true if `--exclude` option gets passed in otherwise false
     */
    protected function hasExclude()
    {
        return $this->input->getOption('exclude');
    }

    /**
     * Checks if the input has the `--only` option
     *
     * @return bool Returns true if `--only` option gets passed in otherwise false
     */
    protected function hasOnly()
    {
        return $this->input->getOption('only');
    }

    /**
     * Checks for the usage of deprecated VERSION as argument when not `all`
     *
     * @return bool True if it is using VERSION argument otherwise false
     */
    protected function isUsingDeprecatedVersion()
    {
        $version = $this->input->getArgument('version');
        return $version && $version !== 'all' && $version !== '*';
    }

    /**
     * Checks for an invalid use of `--exclude` or `--only`
     *
     * @return bool Returns true when it is an invalid use of `--exclude` or `--only` otherwise false
     */
    protected function invalidOnlyOrExclude()
    {
        return ($this->hasExclude() && $this->hasOnly()) ||
            ($this->hasExclude() || $this->hasOnly()) &&
            $this->input->getOption('target') === null;
    }

    /**
     * Outputs the deprecated message for the `all` or `*` usage
     *
     * @return void Just outputs the message
     */
    protected function outputDeprecatedAllMessage()
    {
        $msg = "DEPRECATED: `all` or `*` as version is deprecated. Use `bin/cake migrations mark_migrated` instead";
        $output = $this->output();
        $output->writeln(sprintf("<comment>%s</comment>", $msg));
    }

    /**
     * Outputs the deprecated message for the usage of VERSION as argument
     *
     * @return void Just outputs the message
     */
    protected function outputDeprecatedVersionMessage()
    {
        $msg = 'DEPRECATED: VERSION as argument is deprecated. Use: ' .
            '`bin/cake migrations mark_migrated --target=VERSION --only`';
        $output = $this->output();
        $output->writeln(sprintf("<comment>%s</comment>", $msg));
    }
}
