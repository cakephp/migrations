<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Shell\Task;

use Bake\Shell\Task\BakeTask;
use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Filesystem\File;
use Cake\Utility\Inflector;

/**
 * Task class for generating migrations files.
 */
class MigrationTask extends BakeTask
{

    /**
     * path to Migration directory
     *
     * @var string
     */
    public $pathFragment = 'config/Migrations/';

    /**
     * tasks
     *
     * @var array
     */
    public $tasks = ['Bake.Template'];

    /**
     * Tables to skip
     *
     * @var array
     */
    public $skipTables = ['i18n', 'phinxlog'];

    /**
     * Regex of Table name to skip
     *
     * @var string
     */
    public $skipTablesRegex = '_phinxlog';

    /**
     * Execution method always used for tasks
     *
     * @param string $name The name of the migration file to bake.
     * @return void
     */
    public function main($name = null)
    {
        parent::main();

        if (empty($name)) {
            $this->out('Choose a migration name to bake in underscore format');
            return true;
        }

        $name = $this->_getName($name);
        $name = Inflector::underscore($name);

        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            return $this->error('The filename is not correct. The filename can only contain "a-z", "0-9", "_".');
        }

        $this->bake($name);
    }

    /**
     * Generate code for the given migration name.
     *
     * @param string $filename The migration name to generate.
     * @return void
     */
    public function bake($filename)
    {
        $ns = Configure::read('App.namespace');
        $pluginPath = '';
        if ($this->plugin) {
            $ns = $this->plugin;
            $pluginPath = $this->plugin . '.';
        }

        $collection = $this->getCollection($this->connection);

        $tables = $collection->listTables();
        foreach ($tables as $num => $table) {
            if ((in_array($table, $this->skipTables)) || (strpos($table, $this->skipTablesRegex) !== false)) {
                unset($tables[$num]);
                continue;
            }

            if (!$this->modelToAdd($table, $this->plugin)) {
                unset($tables[$num]);
                continue;
            }
        }

        $data = [
            'plugin' => $this->plugin,
            'pluginPath' => $pluginPath,
            'namespace' => $ns,
            'collection' => $collection,
            'tables' => $tables,
            'name' => Inflector::camelize($filename)
        ];

        $this->Template->set($data);

        $out = $this->Template->generate('Migrations.config/migration');

        $path = dirname(APP) . DS . $this->pathFragment;
        if (isset($this->plugin)) {
            $path = $this->_pluginPath($this->plugin) . $this->pathFragment;
        }
        $path = str_replace('/', DS, $path);
        $filename = $path . date('YmdHis') . '_' . $filename . '.php';
        $message = "\n" . 'Baking migration class for Connection ' . $this->connection;
        if (!empty($this->plugin)) {
            $message .= ' (Plugin : ' . $this->plugin . ')';
        }
        $this->out($message, 1, Shell::QUIET);
        $this->createFile($filename, $out);
        return $out;
    }

    /**
     * Get a collection from a database
     *
     * @param string $connection : database connection name
     * @return obj schemaCollection
     */
    public function getCollection($connection)
    {
        $db = ConnectionManager::get($connection);
        return $db->schemaCollection();
    }

    /**
     * To check if a Table Model is to be added in the migration file
     *
     * @param string $tableName Table name in underscore case
     * @param string $pluginName Plugin name if exists
     * @return bool true if the model is to be added
     */
    public function modelToAdd($tableName, $pluginName = null)
    {
        if ($this->params['checkModel'] === true) {
            if (!$this->modelExist(Inflector::camelize($tableName), $pluginName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * To check if a Table Model exists in the path of model
     *
     * @param string $tableName Table name in underscore case
     * @param string $pluginName Plugin name if exists
     * @return bool
     */
    public function modelExist($tableName, $pluginName = null)
    {
        $file = new File($this->getModelPath($pluginName) . $tableName . 'Table.php');
        if ($file->exists()) {
            return true;
        }
        return false;
    }

    /**
     * Path for Table folder
     *
     * @param string $pluginName Plugin name if exists
     * @return string : path to Table Folder. Default to App Table Path
     */
    public function getModelPath($pluginName = null)
    {
        if (!is_null($pluginName) && Plugin::loaded($pluginName)) {
            return Plugin::classPath($pluginName) . 'Model' . DS . 'Table' . DS;
        }
        return APP . 'Model' . DS . 'Table' . DS;
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();

        $parser->description(
            'Bake migration class.'
        )->addArgument('name', [
            'help' => 'Name of the migration to bake. Can use Plugin.name to bake plugin migrations.',
            'required' => true
        ])->addOption('connection', [
            'short' => 'c',
            'default' => 'default',
            'help' => 'The datasource connection to get data from.'
        ])->addOption('checkModel', [
            'default' => true,
            'help' => 'If model is set to true, check also that the model exists.'
        ])->addOption('theme', [
            'short' => 't',
            'default' => 'Migrations',
            'help' => 'The theme to use when baking code.'
        ])->addOption('plugin', [
            'short' => 'p',
            'help' => 'Plugin to bake into.'
        ])->epilog(
            'Omitting all arguments and options will list the options for and arguments for the plugin'
        );

        return $parser;
    }
}
