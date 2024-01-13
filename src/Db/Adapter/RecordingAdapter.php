<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use Migrations\Db\Action\AddColumn;
use Migrations\Db\Action\AddForeignKey;
use Migrations\Db\Action\AddIndex;
use Migrations\Db\Action\CreateTable;
use Migrations\Db\Action\DropForeignKey;
use Migrations\Db\Action\DropIndex;
use Migrations\Db\Action\DropTable;
use Migrations\Db\Action\RemoveColumn;
use Migrations\Db\Action\RenameColumn;
use Migrations\Db\Action\RenameTable;
use Migrations\Db\Plan\Intent;
use Migrations\Db\Plan\Plan;
use Migrations\Db\Table\Table;
use Migrations\Migration\IrreversibleMigrationException;

/**
 * Recording Proxy Adapter.
 *
 * Used for recording migration commands to automatically reverse them.
 */
class RecordingAdapter extends AdapterWrapper
{
    /**
     * @var \Migrations\Db\Action\Action[]
     */
    protected array $commands = [];

    /**
     * @inheritDoc
     */
    public function getAdapterType(): string
    {
        return 'RecordingAdapter';
    }

    /**
     * @inheritDoc
     */
    public function createTable(Table $table, array $columns = [], array $indexes = []): void
    {
        $this->commands[] = new CreateTable($table);
    }

    /**
     * @inheritDoc
     */
    public function executeActions(Table $table, array $actions): void
    {
        $this->commands = array_merge($this->commands, $actions);
    }

    /**
     * Gets an array of the recorded commands in reverse.
     *
     * @throws \Migrations\Migration\IrreversibleMigrationException if a command cannot be reversed.
     * @return \Migrations\Db\Plan\Intent
     */
    public function getInvertedCommands(): Intent
    {
        $inverted = new Intent();

        foreach (array_reverse($this->commands) as $command) {
            switch (true) {
                case $command instanceof CreateTable:
                    /** @var \Migrations\Db\Action\CreateTable $command */
                    $inverted->addAction(new DropTable($command->getTable()));
                    break;

                case $command instanceof RenameTable:
                    /** @var \Migrations\Db\Action\RenameTable $command */
                    $inverted->addAction(new RenameTable(new Table($command->getNewName()), $command->getTable()->getName()));
                    break;

                case $command instanceof AddColumn:
                    /** @var \Migrations\Db\Action\AddColumn $command */
                    $inverted->addAction(new RemoveColumn($command->getTable(), $command->getColumn()));
                    break;

                case $command instanceof RenameColumn:
                    /** @var \Migrations\Db\Action\RenameColumn $command */
                    $column = clone $command->getColumn();
                    $name = (string)$column->getName();
                    $column->setName($command->getNewName());
                    $inverted->addAction(new RenameColumn($command->getTable(), $column, $name));
                    break;

                case $command instanceof AddIndex:
                    /** @var \Migrations\Db\Action\AddIndex $command */
                    $inverted->addAction(new DropIndex($command->getTable(), $command->getIndex()));
                    break;

                case $command instanceof AddForeignKey:
                    /** @var \Migrations\Db\Action\AddForeignKey $command */
                    $inverted->addAction(new DropForeignKey($command->getTable(), $command->getForeignKey()));
                    break;

                default:
                    throw new IrreversibleMigrationException(sprintf(
                        'Cannot reverse a "%s" command',
                        get_class($command)
                    ));
            }
        }

        return $inverted;
    }

    /**
     * Execute the recorded commands in reverse.
     *
     * @return void
     */
    public function executeInvertedCommands(): void
    {
        $plan = new Plan($this->getInvertedCommands());
        $plan->executeInverse($this->getAdapter());
    }
}
