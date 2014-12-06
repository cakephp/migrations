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
}
