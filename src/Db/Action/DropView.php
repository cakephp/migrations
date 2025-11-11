<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\TableMetadata;

class DropView extends Action
{
    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table metadata
     * @param string $viewName The name of the view to drop
     * @param bool $materialized Whether this is a materialized view (PostgreSQL only)
     */
    public function __construct(
        TableMetadata $table,
        protected string $viewName,
        protected bool $materialized = false,
    ) {
        parent::__construct($table);
    }

    /**
     * Gets the view name
     *
     * @return string
     */
    public function getViewName(): string
    {
        return $this->viewName;
    }

    /**
     * Gets whether this is a materialized view
     *
     * @return bool
     */
    public function getMaterialized(): bool
    {
        return $this->materialized;
    }
}
