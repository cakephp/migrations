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

use Cake\Collection\Collection;
use Phinx\Db\Adapter\SQLiteAdapter;
use Phinx\Db\Table as BaseTable;

class Table extends BaseTable
{

    /**
     * Primary key for this table.
     * Can either be a string or an array in case of composite
     * primary key.
     *
     * @var string|array
     */
    protected $primaryKey;

    /**
     * Add a primary key to a database table.
     *
     * @param string|array $columns Table Column(s)
     * @return Table
     */
    public function addPrimaryKey($columns)
    {
        $this->primaryKey = $columns;
        return $this;
    }

    /**
     * You can pass `autoIncrement` as an option and it will be converted
     * to the correct option for phinx to create the column with an
     * auto increment attribute
     *
     * {@inheritdoc}
     */
    public function addColumn($columnName, $type = null, $options = [])
    {
        if (isset($options['autoIncrement']) && $options['autoIncrement'] === true) {
            $options['identity'] = true;
            unset($options['autoIncrement']);
        }

        return parent::addColumn($columnName, $type, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function create()
    {
        if ((!isset($this->options['id']) || $this->options['id'] === false) && !empty($this->primaryKey)) {
            $this->options['primary_key'] = $this->primaryKey;
            $this->filterPrimaryKey();
        }

        parent::create();
    }

    /**
     * This method is called in case a primary key was defined using the addPrimaryKey() method.
     * It currently does something only if using SQLite.
     * If a column is an auto-increment key in SQLite, it has to be a primary key and it has to defined
     * when defining the column. Phinx takes care of that so we have to make sure columns defined as autoincrement were
     * not added with the addPrimaryKey method, otherwise, SQL queries will be wrong.
     *
     * @return void
     */
    protected function filterPrimaryKey()
    {
        if (!($this->getAdapter() instanceof SQLiteAdapter) || empty($this->options['primary_key'])) {
            return;
        }

        $primaryKey = $this->options['primary_key'];
        if (!is_array($primaryKey)) {
            $primaryKey = [$primaryKey];
        }
        $primaryKey = array_flip($primaryKey);

        $columnsCollection = new Collection($this->columns);
        $primaryKeyColumns = $columnsCollection->filter(function ($columnDef, $key) use ($primaryKey) {
            return isset($primaryKey[$columnDef->getName()]);
        })->toArray();

        if (empty($primaryKeyColumns)) {
            return;
        }

        foreach ($primaryKeyColumns as $primaryKeyColumn) {
            if ($primaryKeyColumn->isIdentity()) {
                unset($primaryKey[$primaryKeyColumn->getName()]);
            }
        }

        $primaryKey = array_flip($primaryKey);

        if (!empty($primaryKey)) {
            $this->options['primary_key'] = $primaryKey;
        } else {
            unset($this->options['primary_key']);
        }
    }
}
