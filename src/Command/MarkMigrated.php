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
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MarkMigrated extends AbstractCommand
{

    use ConfigurationTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mark_migrated')
            ->setDescription('Mark a migration as migrated')
            ->addArgument('version', InputArgument::REQUIRED, 'What is the version of the migration?')
            ->setHelp(sprintf(
                '%sMark a migration migrated based on its version number%s',
                PHP_EOL,
                PHP_EOL
            ));
        $this->addOption('plugin', 'p', InputArgument::OPTIONAL, 'The plugin the file should be created for')
            ->addOption('connection', 'c', InputArgument::OPTIONAL, 'The datasource connection to use')
            ->addOption('source', 's', InputArgument::OPTIONAL, 'The folder where migrations are in');
    }

    /**
     * Mark a migration migrated
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input the input object
     * @param \Symfony\Component\Console\Output\OutputInterface $output the output object
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setInput($input);
        $this->bootstrap($input, $output);

        $path = $this->getConfig()->getMigrationPath();
        $version = $input->getArgument('version');

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
}
