<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\TableMetadata;

abstract class Action
{
    /**
     * @var \Migrations\Db\Table\TableMetadata
     */
    protected TableMetadata $table;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table the Table to apply the action to
     */
    public function __construct(TableMetadata $table)
    {
        $this->table = $table;
    }

    /**
     * The table this action will be applied to
     *
     * @return \Migrations\Db\Table\TableMetadata
     */
    public function getTable(): TableMetadata
    {
        return $this->table;
    }
}
