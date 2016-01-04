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
     * {@inheritDoc}
     */
    public function bake($name)
    {
        $isSynced = $this->_checkSync();

        if (!$isSynced) {
            $this->error('Your migrations history is not in sync with your migrations files. ' .
                'Make sure all your migrations have been migrated before baking a diff.');
        }
    }

    /**
     * Checks that the migrations history is in sync with the migrations files
     *
     * @return bool Whether migrations history is sync or not
     */
    protected function _checkSync()
    {
        $migrationsPath = $this->getPath();
        $migrations = glob($migrationsPath . '*.php');
        $tableName = $this->getPhinxTable($this->plugin);

        $connection = ConnectionManager::get($this->connection);
        $tableExists = in_array($tableName, $connection->schemaCollection()->listTables());

        $migratedItems = [];
        if ($tableExists) {
            $query = $connection->newQuery();
            $migratedItems = $query
                ->select(['version'])
                ->from($tableName)
                ->order(['version DESC'])
                ->limit(1)
                ->execute()->fetchAll('assoc');
        }

        if (empty($migrations) && empty($migratedItems)) {
            return true;
        }

        if (!empty($migratedItems)) {
            $lastVersion = $migratedItems[0]['version'];
            $lastFile = end($migrations);

            return (bool)strpos($lastFile, $lastVersion);
        }

        return false;
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
