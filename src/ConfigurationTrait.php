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

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use Phinx\Config\Config;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Contains a set of methods designed as overrides for
 * the methods in phinx that are responsible for reading the project configuration.
 * This is needed so that we can use the application configuration instead of having
 * a configuration yaml file.
 */
trait ConfigurationTrait
{

    /**
     * The configuration object that phinx uses for connecting to the database
     *
     * @var Phinx\Config\Config
     */
    protected $configuration;

    /**
     * The console input instance
     *
     * @var Symfony\Component\Console\Input\Input
     */
    protected $input;

    /**
     * Overrides the original method from phinx in order to return a tailored
     * Config object containing the connection details for the database.
     *
     * @return Phinx\Config\Config
     */
    public function getConfig()
    {
        if ($this->configuration) {
            return $this->configuration;
        }

        $folder = 'Migrations';
        if ($this->input->getOption('source')) {
            $folder = $this->input->getOption('source');
        }

        $dir = ROOT . DS . 'config' . DS . $folder;
        $plugin = null;

        if ($this->input->getOption('plugin')) {
            $plugin = $this->input->getOption('plugin');
            $dir = Plugin::path($plugin) . 'config' . DS . $folder;
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $plugin = $plugin ? Inflector::underscore($plugin) . '_' : '';
        $plugin = str_replace(array('\\', '/', '.'), '_', $plugin);

        $connection = $this->getConnectionName($this->input);

        $config = ConnectionManager::config($connection);
        return $this->configuration = new Config([
            'paths' => [
                'migrations' => $dir
            ],
            'environments' => [
                'default_migration_table' => $plugin . 'phinxlog',
                'default_database' => 'default',
                'default' => [
                    'adapter' => $this->getAdapterName($config['driver']),
                    'host' => isset($config['host']) ? $config['host'] : null,
                    'user' => isset($config['username']) ? $config['username'] : null,
                    'pass' => isset($config['password']) ? $config['password'] : null,
                    'port' => isset($config['port']) ? $config['port'] : null,
                    'name' => $config['database'],
                    'charset' => isset($config['encoding']) ? $config['encoding'] : null,
                ]
            ]
        ]);
    }

    /**
     * Returns the correct driver name to use in phinx based on the driver class
     * that was configured for the configuration.
     *
     * @param string $driver The driver name as configured for the CakePHP app.
     * @return Phinx\Config\Config
     * @throws \InvalidArgumentexception when it was not possible to infer the information
     * out of the provided database configuration
     */
    public function getAdapterName($driver)
    {
        switch ($driver) {
            case 'Cake\Database\Driver\Mysql':
            case is_subclass_of($driver, 'Cake\Database\Driver\Mysql'):
                return 'mysql';
            case 'Cake\Database\Driver\Postgres':
            case is_subclass_of($driver, 'Cake\Database\Driver\Postgres'):
                return 'pgsql';
            case 'Cake\Database\Driver\Sqlite':
            case is_subclass_of($driver, 'Cake\Database\Driver\Sqlite'):
                return 'sqlite';
            case 'Cake\Database\Driver\Sqlserver':
            case is_subclass_of($driver, 'Cake\Database\Driver\Sqlserver'):
                return 'sqlsrv';
        }

        throw new \InvalidArgumentexception('Could not infer database type from driver');
    }

    /**
     * Overrides the action execute method in order to vanish the idea of environments
     * from phinx. CakePHP does not believe in the idea of having in-app environments
     *
     * @param Symfony\Component\Console\Input\InputInterface $input the input object
     * @param Symfony\Component\Console\Input\OutputInterface $output the output object
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setInput($input);
        $this->addOption('--environment', '-e', InputArgument::OPTIONAL);
        $input->setOption('environment', 'default');
        parent::execute($input, $output);
    }

    /**
     * Sets the input object that should be used for the command class. This object
     * is used to inspect the extra options that are needed for CakePHP apps.
     *
     * @param Symfony\Component\Console\Input\InputInterface $input the input object
     * @return void
     */
    public function setInput(InputInterface $input)
    {
        $this->input = $input;
    }

    /**
     * A callback method that is used to inject the PDO object created from phinx into
     * the CakePHP connection. This is needed in case the user decides to use tables
     * from the ORM and executes queries.
     *
     * @param Symfony\Component\Console\Input\InputInterface $input the input object
     * @param Symfony\Component\Console\Input\OutputInterface $output the output object
     * @return void
     */
    public function bootstrap(InputInterface $input, OutputInterface $output)
    {
        parent::bootstrap($input, $output);
        $connection = ConnectionManager::get($this->getConnectionName($input));
        $pdo = $this->getManager()->getEnvironment('default')->getAdapter()->getConnection();
        $connection->driver()->connection($pdo);
    }

    /**
     * Returns the connection name that should be used for the migrations.
     *
     * @param Symfony\Component\Console\Input\InputInterface $input the input object
     * @return string
     */
    protected function getConnectionName(InputInterface $input)
    {
        $connection = 'default';
        if ($input->getOption('connection')) {
            $connection = $this->input->getOption('connection');
        }
        return $connection;
    }
}
