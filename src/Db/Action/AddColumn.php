<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Literal;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\Table;

class AddColumn extends Action
{
    /**
     * The column to add
     *
     * @var \Migrations\Db\Table\Column
     */
    protected Column $column;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\Table $table The table to add the column to
     * @param \Migrations\Db\Table\Column $column The column to add
     */
    public function __construct(Table $table, Column $column)
    {
        parent::__construct($table);
        $this->column = $column;
    }

    /**
     * Returns a new AddColumn object after assembling the given commands
     *
     * @param \Migrations\Db\Table\Table $table The table to add the column to
     * @param string $columnName The column name
     * @param string|\Migrations\Db\Literal $type The column type
     * @param array<string, mixed> $options The column options
     * @return self
     */
    public static function build(Table $table, string $columnName, string|Literal $type, array $options = []): self
    {
        $column = new Column();
        $column->setName($columnName);
        $column->setType($type);
        $column->setOptions($options); // map options to column methods

        return new AddColumn($table, $column);
    }

    /**
     * Returns the column to be added
     *
     * @return \Migrations\Db\Table\Column
     */
    public function getColumn(): Column
    {
        return $this->column;
    }
}
