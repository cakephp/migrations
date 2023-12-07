<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\Column;
use Migrations\Db\Table\Table;

class RemoveColumn extends Action
{
    /**
     * The column to be removed
     *
     * @var \Migrations\Db\Table\Column
     */
    protected Column $column;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\Table $table The table where the column is
     * @param \Migrations\Db\Table\Column $column The column to be removed
     */
    public function __construct(Table $table, Column $column)
    {
        parent::__construct($table);
        $this->column = $column;
    }

    /**
     * Creates a new RemoveColumn object after assembling the
     * passed arguments.
     *
     * @param \Migrations\Db\Table\Table $table The table where the column is
     * @param string $columnName The name of the column to drop
     * @return self
     */
    public static function build(Table $table, string $columnName): self
    {
        $column = new Column();
        $column->setName($columnName);

        return new RemoveColumn($table, $column);
    }

    /**
     * Returns the column to be dropped
     *
     * @return \Migrations\Db\Table\Column
     */
    public function getColumn(): Column
    {
        return $this->column;
    }
}
