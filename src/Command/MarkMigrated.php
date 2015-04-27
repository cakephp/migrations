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
     * @param Symfony\Component\Console\Input\Inputnterface $input the input object
     * @param Symfony\Component\Console\Input\OutputInterface $output the output object
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setInput($input);
        $this->bootstrap($input, $output);
        $adapter = $this->getManager()->getEnvironment('default')->getAdapter();

        $path = $this->getConfig()->getMigrationPath();
        $version = $input->getArgument('version');

        $versions = array_flip($adapter->getVersions());
        if (isset($versions[$version])) {
            $output->writeln(
                sprintf(
                    '<info>The migration with version number `%s` has already been marked as migrated.</info>',
                    $version
                )
            );
            return;
        }

        $migrationFile = glob($path . DS . $version . '*');
        if (!empty($migrationFile)) {
            $migrationFile = $migrationFile[0];
            $className = $this->getMigrationClassName($migrationFile);
            require_once $migrationFile;
            $Migration = new $className($version);

            $time = date('Y-m-d H:i:s', time());

            try {
                $adapter->migrated($Migration, 'up', $time, $time);
                $output->writeln('<info>Migration successfully marked migrated !</info>');
            } catch (Exception $e) {
                $output->writeln(sprintf('<error>An error occurred : %s</error>', $e->getMessage()));
            }
        } else {
            $output->writeln(
                sprintf('<error>A migration file matching version number `%s` could not be found</error>', $version)
            );
        }
    }

    /**
     * Resolves a migration class name based on $path
     *
     * @param string $path Path to the migration file of which we want the class name
     * @return string Migration class name
     */
    protected function getMigrationClassName($path)
    {
        $class = preg_replace('/^[0-9]+_/', '', basename($path));
        $class = str_replace('_', ' ', $class);
        $class = ucwords($class);
        $class = str_replace(' ', '', $class);
        if (strpos($class, '.') !== false) {
            $class = substr($class, 0, strpos($class, '.'));
        }

        return $class;
    }
}
