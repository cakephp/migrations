<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

use Cake\Database\Schema\Index as DatabaseIndex;
use RuntimeException;

/**
 * Index value object
 *
 * Used to define indexes that are added to tables as part of migrations.
 *
 * @see \Migrations\BaseMigration::index()
 * @see \Migrations\Db\Table::addIndex()
 */
class Index extends DatabaseIndex
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
     * Constructor
     *
     * @param string $name The name of the index.
     * @param array<string> $columns The columns to index.
     * @param string $type The type of index, e.g. 'index', 'fulltext'.
     * @param array<string, int>|int|null $length The length of the index.
     * @param array<string>|null $order The sort order of the index columns.
     * @param array<string>|null $include The included columns for covering indexes.
     * @param ?string $where The where clause for partial indexes.
     * @param bool $concurrent Whether to create the index concurrently.
     */
    public function __construct(
        protected string $name = '',
        protected array $columns = [],
        protected string $type = self::INDEX,
        protected array|int|null $length = null,
        protected ?array $order = null,
        protected ?array $include = null,
        protected ?string $where = null,
        protected bool $concurrent = false,
    ) {
    }

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
     * Sets the index limit.
     *
     * In MySQL indexes can have limit clauses to control the number of
     * characters indexed in text and char columns.
     *
     * @param int|array $limit limit value or array of limit value
     * @return $this
     * @deprecated 5.0 Use setLength() instead.
     */
    public function setLimit(int|array $limit)
    {
        $this->setLength($limit);

        return $this;
    }

    /**
     * Gets the index limit.
     *
     * @return int|array|null
     * @deprecated 5.0 Use getLength() instead.
     */
    public function getLimit(): int|array|null
    {
        return $this->getLength();
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
