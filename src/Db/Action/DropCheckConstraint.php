<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\TableMetadata;

class DropCheckConstraint extends Action
{
    /**
     * The check constraint name to drop
     */
    protected string $constraintName;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to remove the constraint from
     * @param string $constraintName The name of the check constraint to drop
     */
    public function __construct(TableMetadata $table, string $constraintName)
    {
        parent::__construct($table);
        $this->constraintName = $constraintName;
    }

    /**
     * Returns the name of the check constraint to drop
     *
     * @return string
     */
    public function getConstraintName(): string
    {
        return $this->constraintName;
    }
}
