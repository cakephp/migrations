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
namespace Migrations;

use Cake\Console\ShellDispatcher;
use Phinx\Seed\AbstractSeed as BaseAbstractSeed;

/**
 * Class AbstractSeed
 * Extends Phinx base AbstractSeed class in order to extend the features the seed class
 * offers.
 */
abstract class AbstractSeed extends BaseAbstractSeed
{

    /**
     * Gives the ability to a seeder to call another seeder.
     * This is particularly useful if you need to run the seeders of your applications in a specific sequences,
     * for instance to respect foreign key constraints.
     *
     * @param string $seeder Name of the seeder to call from the current seed
     * @return void
     */
    public function call($seeder)
    {
        $argv = [
            'dummy',
            'migrations',
            'seed',
            '--seed',
            $seeder
        ];

        $this->getOutput()->writeln('');
        $this->getOutput()->writeln(
            ' ===='
            . ' <info>' . $seeder . ':</info>'
            . ' <comment>seeding</comment>'
        );

        // Execute the seeder and log the time elapsed.
        $start = microtime(true);
        $dispatcher = new ShellDispatcher($argv);
        $dispatcher->dispatch(['requested' => true]);
        $end = microtime(true);

        $this->getOutput()->writeln(
            ' ===='
            . ' <info>' . $seeder . ':</info>'
            . ' <comment>seeded'
            . ' ' . sprintf('%.4fs', $end - $start) . '</comment>'
        );
        $this->getOutput()->writeln('');
    }
}
