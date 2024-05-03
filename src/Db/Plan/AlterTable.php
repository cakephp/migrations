<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Plan;

use Migrations\Db\Action\Action;
use Migrations\Db\Table\Table;

/**
 * A collection of ALTER actions for a single table
 */
class AlterTable
{
    /**
     * The table
     *
     * @var \Migrations\Db\Table\Table
     */
    protected Table $table;

    /**
     * The list of actions to execute
     *
     * @var \Migrations\Db\Action\Action[]
     */
    protected array $actions = [];

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\Table $table The table to change
     */
    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    /**
     * Adds another action to the collection
     *
     * @param \Migrations\Db\Action\Action $action The action to add
     * @return void
     */
    public function addAction(Action $action): void
    {
        $this->actions[] = $action;
    }

    /**
     * Returns the table associated to this collection
     *
     * @return \Migrations\Db\Table\Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * Returns an array with all collected actions
     *
     * @return \Migrations\Db\Action\Action[]
     */
    public function getActions(): array
    {
        return $this->actions;
    }
}
