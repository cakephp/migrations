<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\TableMetadata;
use Migrations\Db\Table\View;

class CreateView extends Action
{
    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\TableMetadata $table The table metadata
     * @param \Migrations\Db\Table\View $view The view to create
     */
    public function __construct(
        TableMetadata $table,
        protected View $view,
    ) {
        parent::__construct($table);
    }

    /**
     * Gets the view
     *
     * @return \Migrations\Db\Table\View
     */
    public function getView(): View
    {
        return $this->view;
    }
}
