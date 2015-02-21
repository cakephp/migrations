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
use Phinx\Console\Command\Create as CreateCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class Create extends CreateCommand
{

    use ConfigurationTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('create')
            ->setDescription('Create a new migration')
            ->addArgument('name', InputArgument::REQUIRED, 'What is the name of the migration?')
            ->setHelp(sprintf(
                '%sCreates a new database migration file%s',
                PHP_EOL,
                PHP_EOL
            ));
        $this->addOption('plugin', 'p', InputArgument::OPTIONAL, 'The plugin the file should be created for')
            ->addOption('connection', 'c', InputArgument::OPTIONAL, 'The datasource connection to use')
            ->addOption('source', 's', InputArgument::OPTIONAL, 'The folder where migrations are in')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Use an alternative template')
            ->addOption(
                'class',
                'l',
                InputOption::VALUE_REQUIRED,
                'Use a class implementing "' . parent::CREATION_INTERFACE . '" to generate the template'
            );
    }
}
