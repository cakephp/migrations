<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

use RuntimeException;

/**
 * Index value object
 *
 * Used to define indexes that are added to tables as part of migrations.
 *
 * @see \Migrations\BaseMigration::index()
 * @see \Migrations\Db\Table::addIndex()
 */
class Index
{
    /**
     * @var string
     */
    public const UNIQUE = 'unique';

    /**
     * @var string
     */
    public const INDEX = 'index';

    /**
     * @var string
     */
    public const FULLTEXT = 'fulltext';

    /**
     * @var string[]|null
     */
    protected ?array $columns = null;

    /**
     * @var string
     */
    protected string $type = self::INDEX;

    /**
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * @var int|array|null
     */
    protected int|array|null $limit = null;

    /**
     * @var string[]|null
     */
    protected ?array $order = null;

    /**
     * @var string[]|null
     */
    protected ?array $includedColumns = null;

    /**
     * @var bool
     */
    protected bool $concurrent = false;

    /**
     * @var string|null The where clause for partial indexes.
     */
    protected ?string $where = null;

    /**
     * Sets the index columns.
     *
     * @param string|string[] $columns Columns
     * @return $this
     */
    public function setColumns(string|array $columns)
    {
        $this->columns = is_string($columns) ? [$columns] : $columns;

        return $this;
    }

    /**
     * Gets the index columns.
     *
     * @return string[]|null
     */
    public function getColumns(): ?array
    {
        return $this->columns;
    }

    /**
     * Sets the index type.
     *
     * @param string $type Type
     * @return $this
     */
    public function setType(string $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Gets the index type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Sets the index name.
     *
     * @param string $name Name
     * @return $this
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets the index name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Sets the index limit.
     *
     * In MySQL indexes can have limit clauses to control the number of
     * characters indexed in text and char columns.
     *
     * @param int|array $limit limit value or array of limit value
     * @return $this
     */
    public function setLimit(int|array $limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Gets the index limit.
     *
     * @return int|array|null
     */
    public function getLimit(): int|array|null
    {
        return $this->limit;
    }

    /**
     * Sets the index columns sort order.
     *
     * @param string[] $order column name sort order key value pair
     * @return $this
     */
    public function setOrder(array $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Gets the index columns sort order.
     *
     * @return string[]|null
     */
    public function getOrder(): ?array
    {
        return $this->order;
    }

    /**
     * Sets the index included columns for a 'covering index'.
     *
     * In postgres and sqlserver, indexes can define additional non-key
     * columns to build 'covering indexes'. This feature allows you to
     * further optimize well-crafted queries that leverage specific
     * indexes by reading all data from the index.
     *
     * @param string[] $includedColumns Columns
     * @return $this
     */
    public function setInclude(array $includedColumns)
    {
        $this->includedColumns = $includedColumns;

        return $this;
    }

    /**
     * Gets the index included columns.
     *
     * @return string[]|null
     */
    public function getInclude(): ?array
    {
        return $this->includedColumns;
    }

    /**
     * Set the concurrent mode for an index
     *
     * In postgres, concurrent indexes don't take locks, but cannot be run within transactions.
     *
     * @param bool $value The concurrent mode for an index.
     * @return $this
     */
    public function setConcurrently(bool $value)
    {
        $this->concurrent = $value;

        return $this;
    }

    /**
     * Get the concurrent value for an index.
     *
     * @return bool
     */
    public function getConcurrently(): bool
    {
        return $this->concurrent;
    }

    /**
     * Set the where clause for partial indexes.
     *
     * @param ?string $where The where clause for partial indexes.
     * @return $this
     */
    public function setWhere(?string $where)
    {
        $this->where = $where;

        return $this;
    }

    /**
     * Get the where clause for partial indexes.
     *
     * @return ?string
     */
    public function getWhere(): ?string
    {
        return $this->where;
    }

    /**
     * Utility method that maps an array of index options to this object's methods.
     *
     * @param array<string, mixed> $options Options
     * @throws \RuntimeException
     * @return $this
     */
    public function setOptions(array $options)
    {
        // Valid Options
        $validOptions = ['concurrently', 'type', 'unique', 'name', 'limit', 'order', 'include', 'where'];
        foreach ($options as $option => $value) {
            if (!in_array($option, $validOptions, true)) {
                throw new RuntimeException(sprintf('"%s" is not a valid index option.', $option));
            }

            // handle $options['unique']
            if (strcasecmp($option, self::UNIQUE) === 0) {
                if ((bool)$value) {
                    $this->setType(self::UNIQUE);
                }
                continue;
            }

            $method = 'set' . ucfirst($option);
            $this->$method($value);
        }

        return $this;
    }
}
