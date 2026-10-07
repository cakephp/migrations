<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Table;

/**
 * View value object
 *
 * Used to define database views that are added as part of migrations.
 *
 * @see \Migrations\BaseMigration::view()
 * @see \Migrations\Db\Table::createView()
 */
class View
{
    /**
     * Constructor
     *
     * @param string $name The name of the view.
     * @param string $definition The SQL SELECT statement that defines the view.
     * @param bool $replace Whether to replace the view if it already exists.
     * @param bool $materialized Whether this is a materialized view (PostgreSQL only).
     */
    public function __construct(
        protected string $name = '',
        protected string $definition = '',
        protected bool $replace = false,
        protected bool $materialized = false,
    ) {
    }

    /**
     * Sets the view name.
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
     * Gets the view name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the view definition (SELECT statement).
     *
     * @param string $definition Definition
     * @return $this
     */
    public function setDefinition(string $definition)
    {
        $this->definition = $definition;

        return $this;
    }

    /**
     * Gets the view definition.
     *
     * @return string
     */
    public function getDefinition(): string
    {
        return $this->definition;
    }

    /**
     * Sets whether to replace the view if it exists.
     *
     * @param bool $replace Replace flag
     * @return $this
     */
    public function setReplace(bool $replace)
    {
        $this->replace = $replace;

        return $this;
    }

    /**
     * Gets whether to replace the view if it exists.
     *
     * @return bool
     */
    public function getReplace(): bool
    {
        return $this->replace;
    }

    /**
     * Sets whether this is a materialized view (PostgreSQL only).
     *
     * @param bool $materialized Materialized flag
     * @return $this
     */
    public function setMaterialized(bool $materialized)
    {
        $this->materialized = $materialized;

        return $this;
    }

    /**
     * Gets whether this is a materialized view.
     *
     * @return bool
     */
    public function getMaterialized(): bool
    {
        return $this->materialized;
    }
}
