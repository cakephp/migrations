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
use Cake\Event\Event;
use Cake\Event\EventManager;
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
     * @return void
     */
    public function main()
    {
        parent::main();

        $name = array_shift($this->args);

        $className = $this->getMigrationName($name);

        $Collection = $this->getCollection($this->connection);
        EventManager::instance()->attach(function (Event $event) use ($Collection) {
            $event->subject->loadHelper('Migrations.Migration', [
                'Collection' => $Collection
            ]);
        }, 'Bake.initialize');

        if ($this->params['snapshot'] === true) {
            $this->snapshot($className);
        }
        $this->fromCommandLine($className);
    }

    public function fromCommandLine($className)
    {
        $ns = Configure::read('App.namespace');
        $pluginPath = '';
        if ($this->plugin) {
            $ns = $this->plugin;
            $pluginPath = $this->plugin . '.';
        }
        $collection = $this->getCollection($this->connection);

        $action = $this->detectAction($className);
        if ($action === null) {
            $data = [
                'plugin' => $this->plugin,
                'pluginPath' => $pluginPath,
                'namespace' => $ns,
                'collection' => $this->getCollection($this->connection),
                'tables' => [],
                'action' => null,
                'name' => $className
            ];
            return $this->generate($className, 'Migrations.config/skeleton', $data);
        }

        $columns = $this->generateFields($this->args);

        list($action, $table) = $action;
        $data = [
            'plugin' => $this->plugin,
            'pluginPath' => $pluginPath,
            'namespace' => $ns,
            'collection' => $this->getCollection($this->connection),
            'tables' => [$table],
            'action' => $action,
            'columns' => $columns,
            'name' => $className
        ];

        $template = 'skeleton';
        return $this->generate($className, sprintf('Migrations.config/%s', $template), $data);
    }

    public function detectAction($name)
    {
        if (preg_match('/^(Create|Drop)(.*)/', $name, $matches)) {
            $action = strtolower($matches[1]) . '_table';
            $table = Inflector::tableize(Inflector::pluralize($matches[2]));
        } elseif (preg_match('/^(Add).*(?:To)(.*)/', $name, $matches)) {
            $action = 'add_field';
            $table = Inflector::tableize(Inflector::pluralize($matches[2]));
        } elseif (preg_match('/^(Remove).*(?:From)(.*)/', $name, $matches)) {
            $action = 'drop_field';
            $table = Inflector::tableize(Inflector::pluralize($matches[2]));
        } elseif (preg_match('/^(Alter)(.*)/', $name, $matches)) {
            $action = 'alter_table';
            $table = Inflector::tableize(Inflector::pluralize($matches[2]));
        } else {
            return null;
        }

        return [$action, $table];
    }

    public function generateFields($arguments)
    {
        $db = ConnectionManager::get($this->connection);
        $validTypes = [
            'biginteger',
            'binary',
            'boolean',
            'date',
            'datetime',
            'decimal',
            'float',
            'integer',
            'string',
            'text',
            'time',
            'timestamp',
        ];

        $fields = [
            'fields' => [],
            'indexes' => [],
        ];
        foreach ($arguments as $field) {
            if (preg_match('/^(\w*)(?::(\w*))?(?::(\w*))?(?::(\w*))?/', $field, $matches)) {
                $field = $matches[1];
                $type = empty($matches[2]) ? null : $matches[2];
                $length = null;
                $indexType = empty($matches[3]) ? null : $matches[3];
                $indexName = empty($matches[4]) ? null : $matches[4];
                $indexUnique = false;

                if ($type === null || !in_array($type, $validTypes)) {
                    if ($type == 'primary_key') {
                        $type = 'integer';
                        $indexType = 'primary';
                    } elseif ($field == 'id') {
                        $type = 'integer';
                    } elseif (in_array($field, ['created', 'modified', 'updated'])) {
                        $type = 'datetime';
                    } else {
                        $type = 'string';
                    }
                }

                if ($type == 'primary_key') {
                    $type = 'integer';
                    $indexType = 'primary';
                } elseif ($type == 'string') {
                    $length = 255;
                } elseif ($type == 'integer') {
                    $length = 11;
                } elseif ($type == 'biginteger') {
                    $length = 20;
                }

                if ($indexType !== null) {
                    if ($indexType == 'primary') {
                        $indexName = 'PRIMARY';
                        $indexUnique = true;
                        $indexType = null;
                    } elseif ($indexType == 'unique') {
                        $indexUnique = true;
                        $indexType = null;
                    }

                    if (empty($indexName)) {
                        if ($indexUnique) {
                            $indexName = strtoupper('UNIQUE_' . $field);
                        } else {
                            $indexName = strtoupper('BY_' . $field);
                        }
                    }

                    if (!isset($fields['indexes'][$indexName])) {
                        $fields['indexes'][$indexName] = [
                            'columns' => [],
                            'options' => [
                                'unique' => $indexUnique,
                                'name' => $indexName,
                            ],
                        ];
                    }

                    $fields['indexes'][$indexName]['columns'][] = $field;
                    if ($indexType !== null) {
                        $fields['indexes'][$indexName]['options']['type'] = $indexType;
                    }
                }

                $fields['fields'][$field] = [
                    'columnType' => $type,
                    'options' => [
                        'null' => false,
                        'default' => null,
                    ]
                ];

                if ($length !== null) {
                    $fields['fields'][$field]['options']['limit'] = $length;
                }
            }
        }
        return $fields;
    }

    /**
     * Generate code for the given migration name.
     *
     * @param string $className The migration class name to generate.
     * @return void
     */
    public function snapshot($className)
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

        $data = [
            'plugin' => $this->plugin,
            'pluginPath' => $pluginPath,
            'namespace' => $ns,
            'collection' => $collection,
            'tables' => $tables,
            'action' => 'CreateTable',
            'name' => $className
        ];
        return $this->generate($className, 'Migrations.config/snapshot', $data);
    }

    public function generate($className, $template, $data)
    {
        $this->Template->set($data);

        $out = $this->Template->generate($template);

        $path = dirname(APP) . DS . $this->pathFragment;
        if (isset($this->plugin)) {
            $path = $this->_pluginPath($this->plugin) . $this->pathFragment;
        }
        $path = str_replace('/', DS, $path);
        $filename = $path . date('YmdHis') . '_' . Inflector::underscore($className) . '.php';
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
     * Returns a class name for the migration class
     *
     * If the name is invalid, the task will exit
     *
     * @param string $name Name for the generated migration
     * @return string name of the migration file
     */
    protected function getMigrationName($name = null)
    {
        if (empty($name)) {
            return $this->error('Choose a migration name to bake in CamelCase format');
        }

        $name = $this->_getName($name);
        $name = Inflector::camelize($name);

        if (!preg_match('/^[A-Z]{1}[a-zA-Z0-9]+$/', $name)) {
            return $this->error('The className is not correct. The className can only contain "A-Z" and "0-9".');
        }

        return $name;
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
        )->addOption('connection', [
            'short' => 'c',
            'default' => 'default',
            'help' => 'The datasource connection to get data from.'
        ])->addOption('require-table', [
            'boolean' => true,
            'default' => false,
            'help' => 'If model is set to true, check also that the model exists.'
        ])->addOption('snapshot', [
            'boolean' => true,
            'default' => false,
            'help' => 'If specified, the generated migration is a snapshot of the database schema',
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
