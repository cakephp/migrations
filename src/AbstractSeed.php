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

class AbstractSeed extends BaseAbstractSeed
{

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
