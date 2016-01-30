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
use Migrations\Util\UtilTrait;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Task class for generating migration diff files.
 */
class MigrationDiffTask extends SimpleMigrationTask
{
    use SnapshotTrait;
    use UtilTrait;

    /**
     * Array of migrations that have already been migrated
     *
     * @var array
     */
    protected $migratedItems = [];

    /**
     * Path to the migration files
     *
     * @var string
     */
    protected $migrationsPath;

    /**
     * Migration files that are stored in the self::migrationsPath
     *
     * @var array
     */
    protected $migrationsFiles = [];

    /**
     * Name of the phinx log table
     *
     * @var string
     */
    protected $phinxTable;

    /**
     * List the tables the connection currently holds
     *
     * @var array
     */
    protected $tables = [];

    /**
     * Array of \Cake\Database\Schema\Table objects from the dump file which
     * represents the state of the database after the last migrate / rollback command
     *
     * @var array
     */
    protected $dumpSchema;

    /**
     * Array of \Cake\Database\Schema\Table objects from the current state of the database
     *
     * @var array
     */
    protected $currentSchema;

    /**
     * List of the tables that are commonly found in the dump schema and the current schema
     *
     * @var array
     */
    protected $commonTables;

    /**
     * {@inheritDoc}
     */
    public function bake($name)
    {
        $this->setup();

        if (!$this->checkSync()) {
            $this->error('Your migrations history is not in sync with your migrations files. ' .
                'Make sure all your migrations have been migrated before baking a diff.');
        }

        if (empty($this->migrationsFiles) && empty($this->migratedItems)) {
            return $this->bakeSnapshot($name);
        }

        $this->dumpSchema = $this->getDumpSchema();
        $this->currentSchema = $this->getCurrentSchema();
        $this->commonTables = array_intersect_key($this->currentSchema, $this->dumpSchema);

        $diffParams = $this->calculateDiff();
        debug($diffParams);
    }

    protected function calculateDiff()
    {
        $tables = $this->getTables();
        $columns = $this->getColumns();
        $indexes = $this->getIndexes();
        $constraints = $this->getConstraints();

        return [
            'tables' => $tables,
            'columns' => $columns,
            'indexes' => $indexes,
            'constraints' => $constraints
        ];
    }

    protected function getTables()
    {
        return [
            'add' => array_diff_key($this->currentSchema, $this->dumpSchema),
            'remove' => array_diff_key($this->dumpSchema, $this->currentSchema)
        ];
    }

    protected function getColumns()
    {
        $columns = [];
        foreach ($this->commonTables as $table => $currentSchema) {
            $currentColumns = $currentSchema->columns();
            $oldColumns = $this->dumpSchema[$table]->columns();

            $columns[$table] = ['add' => [], 'remove' => []];

            // brand new columns
            $addedColumns = array_diff($currentColumns, $oldColumns);
            foreach ($addedColumns as $columnName) {
                $columns[$table]['add'][$columnName] = $currentSchema->column($columnName);
            }

            // changes in columns meta-data
            foreach ($currentColumns as $columnName) {
                $column = $currentSchema->column($columnName);
                $oldColumn = $this->dumpSchema[$table]->column($columnName);

                if (in_array($columnName, $oldColumns) &&
                    $column !== $oldColumn
                ) {
                    $columns[$table]['changed'][$columnName] = array_diff($column, $oldColumn);
                }
            }

            // columns deletion
            $columns[$table]['remove'] = array_diff($oldColumns, $currentColumns);
        }

        return $columns;
    }

    protected function getConstraints()
    {
        $constraints = [];
        foreach ($this->commonTables as $table => $currentSchema) {
            $currentConstraints = $currentSchema->constraints();
            $oldConstraints = $this->dumpSchema[$table]->constraints();

            $constraints[$table] = ['add' => [], 'remove' => []];

            // brand new constraints
            $addedConstraints = array_diff($currentConstraints, $oldConstraints);
            foreach ($addedConstraints as $constraintName) {
                $constraints[$table]['add'][$constraintName] = $currentSchema->constraint($constraintName);
            }

            // constraints having the same name between new and old schema
            // if present in both, check if they are the same : if not, remove the old one and add the new one
            foreach ($currentConstraints as $constraintName) {
                $constraint = $currentSchema->constraint($constraintName);

                if (in_array($constraintName, $oldConstraints) &&
                    $constraint !== $this->dumpSchema[$table]->constraint($constraintName)
                ) {
                    $constraints[$table]['remove'][] = $constraintName;
                    $constraints[$table]['add'][$constraintName] = $constraint;
                }
            }

            // removed constraints
            $constraints[$table]['remove'] += array_diff($oldConstraints, $currentConstraints);
        }

        return $constraints;
    }

    protected function getIndexes()
    {
        $indexes = [];
        foreach ($this->commonTables as $table => $currentSchema) {
            $currentIndexes = $currentSchema->indexes();
            $oldIndexes = $this->dumpSchema[$table]->indexes();

            $indexes[$table] = ['add' => [], 'remove' => []];

            // brand new indexes
            $addedIndexes = array_diff($currentIndexes, $oldIndexes);
            foreach ($addedIndexes as $indexName) {
                $indexes[$table]['add'][$indexName] = $currentSchema->index($indexName);
            }

            // indexes having the same name between new and old schema
            // if present in both, check if they are the same : if not, remove the old one and add the new one
            foreach ($currentIndexes as $indexName) {
                $index = $currentSchema->index($indexName);

                if (in_array($indexName, $oldIndexes) &&
                    $index !== $this->dumpSchema[$table]->index($indexName)
                ) {
                    $indexes[$table]['remove'][] = $indexName;
                    $indexes[$table]['add'][$indexName] = $index;
                }
            }

            // indexes deletion
            $indexes[$table]['remove'] += array_diff($oldIndexes, $currentIndexes);
        }

        return $indexes;
    }

    /**
     * Sets up everything the baking process needs
     *
     * @return void
     */
    public function setup()
    {
        $this->migrationsPath = $this->getPath();
        $this->migrationsFiles = glob($this->migrationsPath . '*.php');
        $this->phinxTable = $this->getPhinxTable($this->plugin);

        $connection = ConnectionManager::get($this->connection);
        $this->tables = $connection->schemaCollection()->listTables();
        $tableExists = in_array($this->phinxTable, $this->tables);

        $migratedItems = [];
        if ($tableExists) {
            $query = $connection->newQuery();
            $migratedItems = $query
                ->select(['version'])
                ->from($this->phinxTable)
                ->order(['version DESC'])
                ->execute()->fetchAll('assoc');
        }

        $this->migratedItems = $migratedItems;
    }

    /**
     * Checks that the migrations history is in sync with the migrations files
     *
     * @return bool Whether migrations history is sync or not
     */
    protected function checkSync()
    {
        if (empty($this->migrationsFiles) && empty($this->migratedItems)) {
            return true;
        }

        if (!empty($this->migratedItems)) {
            $lastVersion = $this->migratedItems[0]['version'];
            $lastFile = end($this->migrationsFiles);

            return (bool)strpos($lastFile, $lastVersion);
        }

        return false;
    }

    /**
     * Fallback method called to bake a snapshot when the phinx log history is empty and
     * there are no migration files.
     *
     * @return int Value of the snapshot baking dispatch process
     */
    protected function bakeSnapshot($name)
    {
        $this->out('Your migrations history is empty and you do not have any migrations files.');
        $this->out('Falling back to baking a snapshot...');
        $dispatchCommand = 'bake migration_snapshot ' . $name;

        if (!empty($this->params['connection'])) {
            $dispatchCommand .= ' -c ' . $this->params['connection'];
        }
        if (!empty($this->params['plugin'])) {
            $dispatchCommand .= ' -p ' . $this->params['plugin'];
        }

        $dispatch = $this->dispatchShell([
            'command' => $dispatchCommand
        ]);

        if ($dispatch === 1) {
            $this->error('Something went wrong during the snapshot baking. Please try again.');
        }

        return $dispatch;
    }

    protected function getDumpSchema()
    {
        $inputArgs = [];

        if (!empty($this->params['connection'])) {
            $inputArgs['--connection'] = $this->params['connection'];
        }
        if (!empty($this->params['plugin'])) {
            $inputArgs['--plugin'] = $this->params['plugin'];
        }

        $className = '\Migrations\Command\Dump';
        $definition = (new $className())->getDefinition();

        $input = new ArrayInput($inputArgs, $definition);
        $path = $this->getOperationsPath($input) . DS . 'schema-dump';

        if (!file_exists($path)) {
            $this->error('Unable to retrieve the schema dump file. You can create a dump file using the `cake migrations dump` command');
        }

        return unserialize(file_get_contents($path));
    }

    protected function getCurrentSchema()
    {
        $schema = [];

        if (empty($this->tables)) {
            return $schema;
        }

        $collection = ConnectionManager::get($this->connection)->schemaCollection();
        foreach ($this->tables as $table) {
            if (strpos($table, 'phinx') === 0) {
                continue;
            }

            $schema[$table] = $collection->describe($table);
        }

        return $schema;
    }

    /**
     * {@inheritDoc}
     */
    public function template()
    {
        return 'Migrations.config/diff';
    }

    /**
     * {@inheritDoc}
     */
    public function templateData()
    {
        return parent::templateData();
    }
}
