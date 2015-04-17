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
use Cake\ORM\AssociationCollection;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Association\HasOne;
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
     * After the file has been successfully created, we mark the newly
     * created snapshot as applied
     *
     * {@inheritDoc}
     */
    public function createFile($path, $contents)
    {
        $createFile = parent::createFile($path, $contents);

        if ($createFile) {
            $this->markSnapshotApplied($path);
        }

        return $createFile;
    }

    /**
     * Will mark a snapshot created, the snapshot being identified by its
     * full file path.
     *
     * @param string $path Path to the newly created snapshot
     * @return void
     */
    protected function markSnapshotApplied($path)
    {
        $fileName = pathinfo($path, PATHINFO_FILENAME);
        list($version, ) = explode('_', $fileName, 2);

        $argv = $_SERVER['argv'];
        $_SERVER['argv'] = [
            '',
            'migrations',
            'mark_migrated',
            $version
        ];

        $this->_io->out('Marking the snapshot ' . $fileName . ' as migrated...');
        $result = $this->dispatchShell('migrations', 'mark_migrated', $version);
        $_SERVER['argv'] = $argv;
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
        }

        $collection = $this->getCollection($this->connection);
        $tables = $collection->listTables();

        if ($this->params['require-table'] === true) {
            $tableNamesInModel = $this->getTableNames($this->plugin);

            foreach ($tableNamesInModel as $num => $table) {
                if (!in_array($tables[$num], $tables)) {
                    unset($tableNamesInModel[$num]);
                }
            }
            $tables = $tableNamesInModel;
        } else {
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
     * @param string $connection Database connection name.
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
     * @param string $tableName Table name in underscore case.
     * @param string $pluginName Plugin name if exists.
     * @return bool true if the model is to be added.
     */
    public function tableToAdd($tableName, $pluginName = null)
    {
        if (is_null($pluginName)) {
            return true;
        }

        $pluginName = strtolower(str_replace('/', '_', $pluginName)) . '_';
        if (strpos($tableName, $pluginName) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Gets list Tables Names
     *
     * @param string $pluginName Plugin name if exists.
     * @return array
     */
    public function getTableNames($pluginName = null)
    {
        if (!is_null($pluginName) && !Plugin::loaded($pluginName)) {
            return false;
        }
        $list = [];
        $tables = $this->findTables($pluginName);
        foreach ($tables as $num => $table) {
            $list = $list + $this->fetchTableName($table, $pluginName);
        }

        return $list;
    }

    /**
     * Find Table Class
     *
     * @param string $pluginName Plugin name if exists.
     * @return array
     */
    public function findTables($pluginName = null)
    {
        $path = 'Model' . DS . 'Table' . DS;
        if ($pluginName) {
            $path = Plugin::path($pluginName) . 'src' . DS . $path;
        } else {
            $path = APP . $path;
        }

        if (!is_dir($path)) {
            return false;
        }

        $tableDir = new Folder($path);
        $tableDir = $tableDir->find('.*\.php');
        return $tableDir;
    }

    /**
     * fetch TableName From Table Object
     *
     * @param string $className Name of Table Class.
     * @param string $pluginName Plugin name if exists.
     * @return string
     */
    public function fetchTableName($className, $pluginName = null)
    {
        $tables = [];
        $className = str_replace('Table.php', '', $className);
        if (!is_null($pluginName)) {
            $className = $pluginName . '.' . $className;
        }

        $table = TableRegistry::get($className);
        foreach ($table->associations()->keys() as $key) {
            if ($table->associations()->get($key)->type() === 'belongsToMany') {
                $tables[] = $table->associations()->get($key)->_junctionTableName();
            }
        }
        $tables[] = $table->table();

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
