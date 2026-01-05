<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\PartitionDefinition;
use Migrations\Db\Table\TableMetadata;

/**
 * Add a partition to an existing partitioned table
 */
class AddPartition extends Action
{
    /**
     * @var \Migrations\Db\Table\PartitionDefinition
     */
    protected PartitionDefinition $partition;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to add the partition to
     * @param \Migrations\Db\Table\PartitionDefinition $partition The partition definition
     */
    public function __construct(TableMetadata $table, PartitionDefinition $partition)
    {
        parent::__construct($table);
        $this->partition = $partition;
    }

    /**
     * Returns the partition definition to add
     *
     * @return \Migrations\Db\Table\PartitionDefinition
     */
    public function getPartition(): PartitionDefinition
    {
        return $this->partition;
    }
}
