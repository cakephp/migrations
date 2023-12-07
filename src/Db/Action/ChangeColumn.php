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

class ChangeColumn extends Action
{
    /**
     * The column definition
     *
     * @var \Migrations\Db\Table\Column
     */
    protected Column $column;

    /**
     * The name of the column to be changed
     *
     * @var string
     */
    protected string $columnName;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\Table $table The table to alter
     * @param string $columnName The name of the column to change
     * @param \Migrations\Db\Table\Column $column The column definition
     */
    public function __construct(Table $table, string $columnName, Column $column)
    {
        parent::__construct($table);
        $this->columnName = $columnName;
        $this->column = $column;

        // if the name was omitted use the existing column name
        if ($column->getName() === null || strlen((string)$column->getName()) === 0) {
            $column->setName($columnName);
        }
    }

    /**
     * Creates a new ChangeColumn object after building the column definition
     * out of the provided arguments
     *
     * @param \Migrations\Db\Table\Table $table The table to alter
     * @param string $columnName The name of the column to change
     * @param string|\Migrations\Db\Literal $type The type of the column
     * @param array<string, mixed> $options Additional options for the column
     * @return self
     */
    public static function build(Table $table, string $columnName, string|Literal $type, array $options = []): self
    {
        $column = new Column();
        $column->setName($columnName);
        $column->setType($type);
        $column->setOptions($options); // map options to column methods

        return new ChangeColumn($table, $columnName, $column);
    }

    /**
     * Returns the name of the column to change
     *
     * @return string
     */
    public function getColumnName(): string
    {
        return $this->columnName;
    }

    /**
     * Returns the column definition
     *
     * @return \Migrations\Db\Table\Column
     */
    public function getColumn(): Column
    {
        return $this->column;
    }
}
