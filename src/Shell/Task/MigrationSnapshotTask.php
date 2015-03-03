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

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Core\Plugin;
use Cake\Filesystem\File;
use Cake\Utility\Inflector;
use Migrations\Shell\Task\SimpleMigrationTask;

/**
 * Task class for generating migration snapshot files.
 */
class MigrationSnapshotTask extends SimpleMigrationTask
{
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
     * {@inheritDoc}
     */
    public function bake($name)
    {
        $collection = $this->getCollection($this->connection);
        EventManager::instance()->on('Bake.initialize', function (Event $event) use ($collection) {
            $event->subject->loadHelper('Migrations.Migration', [
                'collection' => $collection
            ]);
        });

        return parent::bake($name);
    }

    /**
     * {@inheritDoc}
     */
    public function template()
    {
        return 'Migrations.config/snapshot';
    }

    /**
     * {@inheritDoc}
     */
    public function templateData()
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

            if (!$this->tableToAdd($table, $this->plugin)) {
                unset($tables[$num]);
                continue;
            }
        }

        return [
            'plugin' => $this->plugin,
            'pluginPath' => $pluginPath,
            'namespace' => $ns,
            'collection' => $collection,
            'tables' => $tables,
            'action' => 'create_table',
            'name' => $this->BakeTemplate->viewVars['name'],
        ];
    }

    /**
     * Get a collection from a database
     *
     * @param string $connection : database connection name
     * @return obj schemaCollection
     */
    public function getCollection($connection)
    {
        $connection = ConnectionManager::get($connection);
        return $connection->schemaCollection();
    }

    /**
     * To check if a Table Model is to be added in the migration file
     *
     * @param string $tableName Table name in underscore case
     * @param string $pluginName Plugin name if exists
     * @return bool true if the model is to be added
     */
    public function tableToAdd($tableName, $pluginName = null)
    {
        if ($this->params['require-table'] === true) {
            return $this->tableExists(Inflector::camelize($tableName), $pluginName);
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
    public function tableExists($tableName, $pluginName = null)
    {
        $file = new File($this->getModelPath($pluginName) . $tableName . 'Table.php');
        return $file->exists();
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
            'Bake migration snapshot class.'
        )->addOption('require-table', [
            'boolean' => true,
            'default' => false,
            'help' => 'If require-table is set to true, check also that the table class exists.'
        ]);

        return $parser;
    }
}
