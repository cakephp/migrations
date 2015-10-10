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

use Migrations\ConfigurationTrait;
use Phinx\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
                InputArgument::REQUIRED,
                'What is the version of the migration? Use the special value `all` to mark all migrations as migrated.'
            )
            ->setHelp(sprintf(
                '%sMark a migration migrated based on its version number%s',
                PHP_EOL,
                PHP_EOL
            ));
        $this->addOption('plugin', 'p', InputArgument::OPTIONAL, 'The plugin the file should be created for')
            ->addOption('connection', 'c', InputArgument::OPTIONAL, 'The datasource connection to use')
            ->addOption('source', 's', InputArgument::OPTIONAL, 'The folder where migrations are in')
            ->addOption('skip-to', 'u', InputArgument::OPTIONAL, 'If present it will mark all versions until the given one')
            ->addOption('up-to', 't', InputArgument::OPTIONAL, 'If present it will mark all versions including the given one');
    }

    /**
     * Mark a migration migrated
     * If the `version` argument has the value `all`, all migrations found will be marked as
     * migrated
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
        $version = $input->getArgument('version');

        if ($this->isBatchMarking($input)) {
            $versions = $this->versionsToMark($input);
            $this->markVersionsAsMigrated($path, $versions);
            return;
        }

        if ($this->getManager()->isMigrated($version)) {
            $output->writeln(
                sprintf(
                    '<info>The migration with version number `%s` has already been marked as migrated.</info>',
                    $version
                )
            );
            return;
        }

        try {
            $this->getManager()->markMigrated($version, $path);
            $output->writeln('<info>Migration successfully marked migrated !</info>');
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>An error occurred : %s</error>', $e->getMessage()));
        }
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
     * Decides based on $input if it will try to mark more than one migration
     *
     * @param InputInterface $input
     * @return boolean
     */
    protected function isBatchMarking(InputInterface $input)
    {
        $version = $input->getArgument('version');

        return $this->isAllVersion($version) ||
            $this->isSkipTo($input) ||
            $this->isUpTo($input);
    }

    /**
     * Decides based on $input which versions it should mark as migrated
     *
     * @param InputInterface $input
     * @return array of versions that should be marked as migrated
     */
    protected function versionsToMark(InputInterface $input)
    {
        $version = $input->getArgument('version');
        $migrations = $this->getManager()->getMigrations();
        $versions = array_keys($migrations);

        if ($this->isAllVersion($version)) {
            return $versions;
        }

        $lengthIncrease = $this->isSkipTo($input) ? 0 : 1;
        $index = array_search($version, $versions) + $lengthIncrease;

        return array_slice($versions, 0, $index);
    }

    /**
     * Checks if the $version is for all migrations
     *
     * @param string $version
     * @return boolean
     */
    protected function isAllVersion($version)
    {
        return $version == 'all' || $version == '*';
    }

    /**
     * Checks if the $input has skip-to option
     *
     * @param InputInterface $input
     * @return boolean
     */
    protected function isSkipTo(InputInterface $input)
    {
        return NULL !== $input->getOption('skip-to');
    }

    /**
     * Checks if the $input has up-to option
     *
     * @param InputInterface $input
     * @return boolean
     */
    protected function isUpTo(InputInterface $input)
    {
        return NULL !== $input->getOption('up-to');
    }
}
