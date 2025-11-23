<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

use Migrations\Db\Literal;

/**
 * Partition value object
 *
 * Defines table partitioning configuration.
 */
class Partition
{
    public const TYPE_RANGE = 'RANGE';
    public const TYPE_RANGE_COLUMNS = 'RANGE COLUMNS'; // MySQL: for DATE/STRING columns
    public const TYPE_LIST = 'LIST';
    public const TYPE_LIST_COLUMNS = 'LIST COLUMNS'; // MySQL: for STRING columns
    public const TYPE_HASH = 'HASH';
    public const TYPE_KEY = 'KEY'; // MySQL only

    /**
     * @param string $type Partition type (RANGE, LIST, HASH, KEY)
     * @param string|string[]|\Migrations\Db\Literal $columns Column(s) or expression to partition by
     * @param \Migrations\Db\Table\PartitionDefinition[] $definitions Partition definitions
     * @param int|null $count Number of partitions (for HASH/KEY)
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        protected string $type,
        protected string|array|Literal $columns,
        protected array $definitions = [],
        protected ?int $count = null,
        protected array $options = [],
    ) {
    }

    /**
     * Get the partition type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the columns or expression used for partitioning.
     *
     * @return string[]|\Migrations\Db\Literal
     */
    public function getColumns(): array|Literal
    {
        if ($this->columns instanceof Literal) {
            return $this->columns;
        }

        return is_string($this->columns) ? [$this->columns] : $this->columns;
    }

    /**
     * Get the partition definitions.
     *
     * @return \Migrations\Db\Table\PartitionDefinition[]
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * Get the partition count (for HASH/KEY types).
     *
     * @return int|null
     */
    public function getCount(): ?int
    {
        return $this->count;
    }

    /**
     * Get additional options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Add a partition definition.
     *
     * @param \Migrations\Db\Table\PartitionDefinition $definition The partition definition
     * @return $this
     */
    public function addDefinition(PartitionDefinition $definition)
    {
        $this->definitions[] = $definition;

        return $this;
    }
}
