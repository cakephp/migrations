<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\Index;
use Migrations\Db\Table\Table;

class DropIndex extends Action
{
    /**
     * The index to drop
     *
     * @var \Migrations\Db\Table\Index
     */
    protected Index $index;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\Table $table The table owning the index
     * @param \Migrations\Db\Table\Index $index The index to be dropped
     */
    public function __construct(Table $table, Index $index)
    {
        parent::__construct($table);
        $this->index = $index;
    }

    /**
     * Creates a new DropIndex object after assembling the passed
     * arguments.
     *
     * @param \Migrations\Db\Table\Table $table The table where the index is
     * @param string[] $columns the indexed columns
     * @return self
     */
    public static function build(Table $table, array $columns = []): self
    {
        $index = new Index();
        $index->setColumns($columns);

        return new DropIndex($table, $index);
    }

    /**
     * Creates a new DropIndex when the name of the index to drop
     * is known.
     *
     * @param \Migrations\Db\Table\Table $table The table where the index is
     * @param string $name The name of the index
     * @return self
     */
    public static function buildFromName(Table $table, string $name): self
    {
        $index = new Index();
        $index->setName($name);

        return new DropIndex($table, $index);
    }

    /**
     * Returns the index to be dropped
     *
     * @return \Migrations\Db\Table\Index
     */
    public function getIndex(): Index
    {
        return $this->index;
    }
}
