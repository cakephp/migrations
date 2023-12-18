<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Plan;

use Migrations\Db\Action\Action;

/**
 * An intent is a collection of actions for many tables
 */
class Intent
{
    /**
     * List of actions to be executed
     *
     * @var \Migrations\Db\Action\Action[]
     */
    protected array $actions = [];

    /**
     * Adds a new action to the collection
     *
     * @param \Migrations\Db\Action\Action $action The action to add
     * @return void
     */
    public function addAction(Action $action): void
    {
        $this->actions[] = $action;
    }

    /**
     * Returns the full list of actions
     *
     * @return \Migrations\Db\Action\Action[]
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Merges another Intent object with this one
     *
     * @param \Migrations\Db\Plan\Intent $another The other intent to merge in
     * @return void
     */
    public function merge(Intent $another): void
    {
        $this->actions = array_merge($this->actions, $another->getActions());
    }
}
