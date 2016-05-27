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

use Migrations\Command\Seed;
use Phinx\Seed\AbstractSeed as BaseAbstractSeed;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Class AbstractSeed
 * Extends Phinx base AbstractSeed class in order to extend the features the seed class
 * offers.
 */
abstract class AbstractSeed extends BaseAbstractSeed
{
    /**
     * Instance of MigrationsDispatcher
     * It is a light-weight instance of the application (it only contains the Seed command)
     * with custom settings
     *
     * @var MigrationsDispatcher
     */
    protected $app;

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
        $this->getOutput()->writeln('');
        $this->getOutput()->writeln(
            ' ===='
            . ' <info>' . $seeder . ':</info>'
            . ' <comment>seeding</comment>'
        );

        // Execute the seeder and log the time elapsed.
        $start = microtime(true);
        $this->runCall($seeder);
        $end = microtime(true);

        $this->getOutput()->writeln(
            ' ===='
            . ' <info>' . $seeder . ':</info>'
            . ' <comment>seeded'
            . ' ' . sprintf('%.4fs', $end - $start) . '</comment>'
        );
        $this->getOutput()->writeln('');
    }

    /**
     * Calls another seeder from this seeder.
     * It will start up a new shell light-weight application (only the and
     *
     * @param string $seeder Name of the seeder to call from the current seed
     * @return void
     */
    protected function runCall($seeder)
    {
        $argv = [
            'migrations',
            'seed',
            '--seed',
            $seeder
        ];
        $input = new ArgvInput($argv);

        $this->getApp()->run($input);
    }

    /**
     * Get the specific MigrationsDispatcher instance needed to run a self::call() call
     *
     * @return MigrationsDispatcher
     */
    protected function getApp()
    {
        if ($this->app === null) {
            $this->app = new MigrationsDispatcher(PHINX_VERSION);
            $this->app->setAutoExit(false);
            $this->app->setCatchExceptions(false);
            $this->app->setRequested(true);

            $seedCommand = new Seed();
            $seedCommand->setRequested(true);
            $this->app->add($seedCommand);
        }

        return $this->app;
    }
}
