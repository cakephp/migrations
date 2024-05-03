<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\Index;
use Migrations\Db\Table\Table;

class AddIndex extends Action
{
    /**
     * The index to add to the table
     *
     * @var \Migrations\Db\Table\Index
     */
    protected Index $index;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\Table $table The table to add the index to
     * @param \Migrations\Db\Table\Index $index The index to be added
     */
    public function __construct(Table $table, Index $index)
    {
        parent::__construct($table);
        $this->index = $index;
    }

    /**
     * Creates a new AddIndex object after building the index object with the
     * provided arguments
     *
     * @param \Migrations\Db\Table\Table $table The table to add the index to
     * @param string|string[]|\Migrations\Db\Table\Index $columns The columns to index
     * @param array<string, mixed> $options Additional options for the index creation
     * @return self
     */
    public static function build(Table $table, string|array|Index $columns, array $options = []): self
    {
        // create a new index object if strings or an array of strings were supplied
        if (!($columns instanceof Index)) {
            $index = new Index();

            $index->setColumns($columns);
            $index->setOptions($options);
        } else {
            $index = $columns;
        }

        return new AddIndex($table, $index);
    }

    /**
     * Returns the index to be added
     *
     * @return \Migrations\Db\Table\Index
     */
    public function getIndex(): Index
    {
        return $this->index;
    }
}
