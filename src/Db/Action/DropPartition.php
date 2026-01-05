<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\TableMetadata;

/**
 * Drop a partition from an existing partitioned table
 */
class DropPartition extends Action
{
    /**
     * @var string
     */
    protected string $partitionName;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to drop the partition from
     * @param string $partitionName The name of the partition to drop
     */
    public function __construct(TableMetadata $table, string $partitionName)
    {
        parent::__construct($table);
        $this->partitionName = $partitionName;
    }

    /**
     * Returns the partition name to drop
     *
     * @return string
     */
    public function getPartitionName(): string
    {
        return $this->partitionName;
    }
}
