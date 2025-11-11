<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\TableMetadata;

class DropTrigger extends Action
{
    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table metadata
     * @param string $triggerName The name of the trigger to drop
     */
    public function __construct(
        TableMetadata $table,
        protected string $triggerName,
    ) {
        parent::__construct($table);
    }

    /**
     * Gets the trigger name
     *
     * @return string
     */
    public function getTriggerName(): string
    {
        return $this->triggerName;
    }
}
