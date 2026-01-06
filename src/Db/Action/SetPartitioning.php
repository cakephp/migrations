<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\Partition;
use Migrations\Db\Table\TableMetadata;

/**
 * Add partitioning to an existing non-partitioned table
 */
class SetPartitioning extends Action
{
    /**
     * @var \Migrations\Db\Table\Partition
     */
    protected Partition $partition;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table to add partitioning to
     * @param \Migrations\Db\Table\Partition $partition The partition configuration
     */
    public function __construct(TableMetadata $table, Partition $partition)
    {
        parent::__construct($table);
        $this->partition = $partition;
    }

    /**
     * Returns the partition configuration
     *
     * @return \Migrations\Db\Table\Partition
     */
    public function getPartition(): Partition
    {
        return $this->partition;
    }
}
