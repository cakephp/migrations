<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Plan;

use Migrations\Db\Table\Column;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\Table;

/**
 * Represents the collection of actions for creating a new table
 */
class NewTable
{
    /**
     * The table to create
     *
     * @var \Migrations\Db\Table\Table
     */
    protected Table $table;

    /**
     * The list of columns to add
     *
     * @var \Migrations\Db\Table\Column[]
     */
    protected array $columns = [];

    /**
     * The list of indexes to create
     *
     * @var \Migrations\Db\Table\Index[]
     */
    protected array $indexes = [];

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\Table $table The table to create
     */
    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    /**
     * Adds a column to the collection
     *
     * @param \Migrations\Db\Table\Column $column The column description
     * @return void
     */
    public function addColumn(Column $column): void
    {
        $this->columns[] = $column;
    }

    /**
     * Adds an index to the collection
     *
     * @param \Migrations\Db\Table\Index $index The index description
     * @return void
     */
    public function addIndex(Index $index): void
    {
        $this->indexes[] = $index;
    }

    /**
     * Returns the table object associated to this collection
     *
     * @return \Migrations\Db\Table\Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * Returns the columns collection
     *
     * @return \Migrations\Db\Table\Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Returns the indexes collection
     *
     * @return \Migrations\Db\Table\Index[]
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }
}
