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
namespace Migrations\Shell;

use Cake\Console\Shell;
use Migrations\MigrationsDispatcher;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * A wrapper shell for phinx migrations, used to inject our own
 * console actions so that database configuration already defined
 * for the application can be reused.
 */
class MigrationsShell extends Shell
{

    /**
     * Array of arguments to run the shell with.
     *
     * @var array
     */
    public $argv = [];

    /**
     * Defines what options can be passed to the shell.
     * This is required because CakePHP validates the passed options
     * and would complain if something not configured here is present
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        return parent::getOptionParser()
            ->addOption('plugin', ['short' => 'p'])
            ->addOption('target', ['short' => 't'])
            ->addOption('connection', ['short' => 'c'])
            ->addOption('source', ['short' => 's'])
            ->addOption('ansi')
            ->addOption('no-ansi')
            ->addOption('version', ['short' => 'V'])
            ->addOption('no-interaction', ['short' => 'n'])
            ->addOption('template', ['short' => 't'])
            ->addOption('format', ['short' => 'f']);
    }

    /**
     * Defines constants that are required by phinx to get running
     *
     * @return void
     */
    public function initialize()
    {
        if (!defined('PHINX_VERSION')) {
            define('PHINX_VERSION', (0 === strpos('@PHINX_VERSION@', '@PHINX_VERSION')) ? '0.4.1' : '@PHINX_VERSION@');
        }
        parent::initialize();
    }

    /**
     * This acts as a front-controller for phinx. It just instantiates the classes
     * responsible for parsing the command line from phinx and gives full control of
     * the rest of the flow to it.
     *
     * The input parameter of the ``MigrationDispatcher::run()`` method is manually built
     * in case a MigrationsShell is dispatched using ``Shell::dispatch()``.
     *
     * @return void
     */
    public function main()
    {
        $app = new MigrationsDispatcher(PHINX_VERSION);
        $input = new ArgvInput($this->argv);
        $app->run($input);
    }

    /**
     * Override the default behavior to save the command called
     * in order to pass it to the command dispatcher
     *
     * {@inheritDoc}
     */
    public function runCommand($argv, $autoMethod = false, $extra = [])
    {
        array_unshift($argv, 'migrations');
        $this->argv = $argv;
        return parent::runCommand($argv, $autoMethod, $extra);
    }

    /**
     * Display the help in the correct format
     *
     * @param string $command The command to get help for.
     * @return void
     */
    protected function displayHelp($command)
    {
        $command;
        $this->main();
    }

    /**
     * {@inheritDoc}
     */
    // @codingStandardsIgnoreStart
    protected function _displayHelp($command)
    {
        // @codingStandardsIgnoreEnd
        return $this->displayHelp($command);
    }
}
