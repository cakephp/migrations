<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\Table;

abstract class Action
{
    /**
     * @var \Migrations\Db\Table\Table
     */
    protected Table $table;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\Table $table the Table to apply the action to
     */
    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    /**
     * The table this action will be applied to
     *
     * @return \Migrations\Db\Table\Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }
}
