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

use Migrations\Command\Migrate;
use Migrations\Command\Rollback;
use Migrations\Command\Status;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * The Migrations class is responsible for handling migrations command
 * within an none-shell application.
 */
class Migrations {

    use ConfigurationTrait;

    /**
     * The OutputInterface.
     * Should be a \Symfony\Component\Console\Output\NullOutput instance
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * The last error caught.
     *
     * @var string
     */
    protected $lastError;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->output = new NullOutput();
    }

    /**
     * Returns the status of each migrations based on the options passed
     *
     * @param array $options Options to pass to the command
     * Available options are :
     *
     * - `format` Format to output the response. Can be 'json'
     * - `connection` The datasource connection to use
     * - `source` The folder where migrations are in
     * - `plugin` The plugin containing the migrations
     *
     * @return array The migrations list and their statuses
     */
    public function status($options = [])
    {
        $options = $this->prepareOptions($options);
        $definition = (new Status())->getDefinition();
        $input = new ArrayInput($options, $definition);

        $params = ['default', $input->getOption('format')];

        return $this->run('printStatus', $params, $input);
    }

    /**
     * Migrates available migrations
     *
     * @param array $options Options to pass to the command
     * Available options are :
     *
     * - `target` The version number to migrate to. If not provided, will migrate
     * everything it can
     * - `connection` The datasource connection to use
     * - `source` The folder where migrations are in
     * - `plugin` The plugin containing the migrations
     *
     * @return bool Success
     */
    public function migrate($options = [])
    {
        $options = $this->prepareOptions($options);
        $definition = (new Migrate())->getDefinition();
        $input = new ArrayInput($options, $definition);

        $params = ['default', $input->getOption('target')];

        try {
            $this->run('migrate', $params, $input);
            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Rollbacks migrations
     *
     * @param array $options Options to pass to the command
     * Available options are :
     *
     * - `target` The version number to migrate to. If not provided, will only migrate
     * the last migrations registered in the phinx log
     * - `connection` The datasource connection to use
     * - `source` The folder where migrations are in
     * - `plugin` The plugin containing the migrations
     *
     * @return bool Success
     */
    public function rollback($options = [])
    {
        $options = $this->prepareOptions($options);
        $definition = (new Rollback())->getDefinition();
        $input = new ArrayInput($options, $definition);

        $params = ['default', $input->getOption('target')];

        try {
            $this->run('rollback', $params, $input);
            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Runs the method needed to execute and return
     *
     * @param string $method Manager method to call
     * @param array $params Manager params to pass
     * @param \Symfony\Component\Console\Input\InputInterface InputInterface needed for the
     * Manager to properly run
     *
     * @return mixed The result of the CakeManager::$method() call
     */
    protected function run($method, $params, $input)
    {
        $this->setInput($input);
        $config = $this->getConfig();
        $manager = new CakeManager($config, $this->output);

        return call_user_func_array([$manager, $method], $params);
    }

    /**
     * Prepares the option to pass on to the InputInterface
     *
     * @param array $options Simple key-values array to pass to the InputInterface
     * @return array Prepared $options
     */
    protected function prepareOptions($options = [])
    {
        if (empty($options)) {
            return $options;
        }

        foreach ($options as $name => $value) {
            $options['--' . $name] = $value;
            unset($options[$name]);
        }

        return $options;
    }

    /**
     * Get the last error message caught while migrating
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }
}