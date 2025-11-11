<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\TableMetadata;
use Migrations\Db\Table\Trigger;

class CreateTrigger extends Action
{
    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table metadata
     * @param \Migrations\Db\Table\Trigger $trigger The trigger to create
     */
    public function __construct(
        TableMetadata $table,
        protected Trigger $trigger,
    ) {
        parent::__construct($table);
    }

    /**
     * Gets the trigger
     *
     * @return \Migrations\Db\Table\Trigger
     */
    public function getTrigger(): Trigger
    {
        return $this->trigger;
    }
}
