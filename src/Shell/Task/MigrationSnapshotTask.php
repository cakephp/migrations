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
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Filesystem\Folder;
use Cake\ORM\TableRegistry;
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
     * List of Plugin Tables name
     *
     * @var string
     */
    public $pluginTables = [];

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
        $namespace = Configure::read('App.namespace');
        $pluginPath = '';
        if ($this->plugin) {
            $namespace = $this->_pluginNamespace($this->plugin);
            $pluginPath = $this->plugin . '.';
            $this->pluginTables = $this->getPluginTables($this->plugin);
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
            'namespace' => $namespace,
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

            if (in_array($tableName, $this->pluginTables)) {
                return true;
            }

            return false;
        }

        $pluginName = strtolower(str_replace('/', '_', $pluginName)) . '_';
        if (strpos($tableName, $pluginName) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Gets list Plugin Tables Names
     *
     * @param string $pluginName Plugin name if exists
     * @return array
     */
    public function getPluginTables($pluginName = null)
    {
        if (is_null($pluginName) && !Plugin::loaded($pluginName)) {
            return false;
        }

        $path = Plugin::path($pluginName) . 'src' . DS . 'Model' . DS . 'Table' . DS;
        if (!is_dir($path)) {
            return false;
        }

        $tableDir = new Folder($path);
        $tables = $tableDir->find('.*\.php');
        foreach($tables as $num => $table) {
            $table = $pluginName . '.' . str_replace('Table.php', '', $table);
            $table = TableRegistry::get($table);
            $tables[$num] = $table->table();
        }

        return $tables;
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
