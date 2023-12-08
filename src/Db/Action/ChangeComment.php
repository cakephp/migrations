<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Action;

use Migrations\Db\Table\Table;

class ChangeComment extends Action
{
    /**
     * The new comment for the table
     *
     * @var string|null
     */
    protected ?string $newComment = null;

    /**
     * Constructor
     *
     * @param \Migrations\Db\Table\Table $table The table to be changed
     * @param string|null $newComment The new comment for the table
     */
    public function __construct(Table $table, ?string $newComment)
    {
        parent::__construct($table);
        $this->newComment = $newComment;
    }

    /**
     * Return the new comment for the table
     *
     * @return string|null
     */
    public function getNewComment(): ?string
    {
        return $this->newComment;
    }
}
