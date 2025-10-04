<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Plan;

use Migrations\Db\Action\Action;
use Migrations\Db\Table\TableMetadata;

/**
 * A collection of ALTER actions for a single table
 */
class AlterTable
{
    /**
     * The table
     *
     * @var \Migrations\Db\Table\TableMetadata
     */
    protected TableMetadata $table;

    /**
     * The list of actions to execute
     *
     * @var \Migrations\Db\Action\Action[]
     */
    protected array $actions = [];

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to change
     */
    public function __construct(TableMetadata $table)
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
     * @return \Migrations\Db\Table\TableMetadata
     */
    public function getTable(): TableMetadata
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
