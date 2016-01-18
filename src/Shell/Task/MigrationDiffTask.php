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
        $tableExists = in_array($this->phinxTable, $connection->schemaCollection()->listTables());

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
