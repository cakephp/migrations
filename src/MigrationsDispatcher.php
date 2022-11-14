<?php
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

use Migrations\Command;
use Symfony\Component\Console\Application;

/**
 * Used to register all supported subcommand in order to make
 * them executable by the Symfony Console component
 */
class MigrationsDispatcher extends Application
{

    /**
     * Class Constructor.
     *
     * Initialize the Phinx console application.
     *
     * @param string $version The Application Version
     */
    public function __construct($version)
    {
        parent::__construct('Migrations plugin, based on Phinx by Rob Morgan.', $version);
        $this->add(new Command\Create());
        $this->add(new Command\Migrate());
        $this->add(new Command\Rollback());
        $this->add(new Command\Status());
        $this->add(new Command\MarkMigrated());
    }
}
