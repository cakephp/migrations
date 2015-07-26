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
use Phinx\Console\Command\Status as StatusCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Status extends StatusCommand
{

    use ConfigurationTrait;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('status')
            ->setDescription('Show migration status')
            ->addOption('--format', '-f', InputArgument::OPTIONAL, 'The output format: text or json. Defaults to text.')
            ->setHelp('prints a list of all migrations, along with their current status')
            ->addOption('--plugin', '-p', InputArgument::OPTIONAL, 'The plugin containing the migrations')
            ->addOption('--connection', '-c', InputArgument::OPTIONAL, 'The datasource connection to use')
            ->addOption('--source', '-s', InputArgument::OPTIONAL, 'The folder where migrations are in');
    }

    /**
     * Show the migration status.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->beforeExecute($input, $output);
        $this->bootstrap($input, $output);

        $environment = $input->getOption('environment');
        $format = $input->getOption('format');

        if (null === $environment) {
            $environment = $this->getManager()->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }
        if (null !== $format) {
            $output->writeln('<info>using format</info> ' . $format);
        }

        // print the status
        $migrations = $this->getManager()->printStatus($environment, $format);

        switch ($format) {
            case 'json':
                $output->writeln($migrations);
                break;
            default:
                $this->display($migrations);
                break;
        }
    }

    /**
     * Will output the status of the migrations
     *
     * @param array $migrations
     * @return void
     */
    protected function display(array $migrations)
    {
        $output = $this->getManager()->getOutput();

        if (!empty($migrations)) {
            $output->writeln('');
            $output->writeln(' Status  Migration ID    Migration Name ');
            $output->writeln('-----------------------------------------');

            foreach ($migrations as $migration) {
                $status = $migration['status'] === 'up' ? '     <info>up</info> ' : '   <error>down</error> ';
                $name = $migration['name'] !== false ?
                    ' <comment>' . $migration['name'] . ' </comment>' :
                    ' <error>** MISSING **</error>';

                $output->writeln(
                    $status
                    . sprintf(' %14.0f ', $migration['id'])
                    . $name
                );
            }

            $output->writeln('');
        } else {
            $msg = 'There are no available migrations. Try creating one using the <info>create</info> command.';
            $output->writeln('');
            $output->writeln($msg);
            $output->writeln('');
        }
    }
}
