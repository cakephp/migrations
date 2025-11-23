<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

/**
 * Individual partition definition
 *
 * For RANGE: value is the upper bound (VALUES LESS THAN)
 * For LIST: value is array of values (VALUES IN)
 * For HASH/KEY: definitions not needed, use count instead
 */
class PartitionDefinition
{
    /**
     * @param string $name Partition name
     * @param mixed $value Boundary value (LESS THAN for RANGE, IN for LIST)
     * @param string|null $tablespace Optional tablespace
     * @param string|null $table Override table name (PostgreSQL only)
     * @param string|null $comment Optional comment
     */
    public function __construct(
        protected string $name,
        protected mixed $value = null,
        protected ?string $tablespace = null,
        protected ?string $table = null,
        protected ?string $comment = null,
    ) {
    }

    /**
     * Get the partition name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the boundary value.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Get the tablespace.
     *
     * @return string|null
     */
    public function getTablespace(): ?string
    {
        return $this->tablespace;
    }

    /**
     * Get the override table name (PostgreSQL only).
     *
     * @return string|null
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Get the partition comment.
     *
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }
}
