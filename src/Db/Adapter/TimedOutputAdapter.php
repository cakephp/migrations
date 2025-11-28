<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db\Adapter;

use BadMethodCallException;
use Cake\Console\ConsoleIo;
use Migrations\Db\InsertMode;
use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\Db\Table\Index;
use Migrations\Db\Table\TableMetadata;

/**
 * Wraps any adapter to record the time spend executing its commands
 */
class TimedOutputAdapter extends AdapterWrapper implements DirectActionInterface
{
    /**
     * @inheritDoc
     */
    public function getAdapterType(): string
    {
        return $this->getAdapter()->getAdapterType();
    }

    /**
     * Start timing a command.
     *
     * @return callable A function that is to be called when the command finishes
     */
    public function startCommandTimer(): callable
    {
        $started = microtime(true);

        return function () use ($started): void {
            $end = microtime(true);
            $this->getIo()?->verbose('    -> ' . sprintf('%.4fs', $end - $started));
        };
    }

    /**
     * Write a command to the output.
     *
     * @param string $command Command Name
     * @param array $args Command Args
     * @return void
     */
    public function writeCommand(string $command, array $args = []): void
    {
        $io = $this->getIo();
        if ($io && $io->level() < ConsoleIo::VERBOSE) {
            return;
        }
        if (count($args)) {
            $outArr = [];
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    $arg = array_map(
                        function ($value) {
                            return '\'' . $value . '\'';
                        },
                        $arg,
                    );
                    $outArr[] = '[' . implode(', ', $arg) . ']';
                    continue;
                }

                $outArr[] = '\'' . $arg . '\'';
            }
            $this->getIo()?->verbose(' -- ' . $command . '(' . implode(', ', $outArr) . ')');

            return;
        }

        $this->getIo()->verbose(' -- ' . $command);
    }

    /**
     * @inheritDoc
     */
    public function insert(
        TableMetadata $table,
        array $row,
        ?InsertMode $mode = null,
        ?array $updateColumns = null,
        ?array $conflictColumns = null,
    ): void {
        $end = $this->startCommandTimer();
        $this->writeCommand('insert', [$table->getName()]);
        parent::insert($table, $row, $mode, $updateColumns, $conflictColumns);
        $end();
    }

    /**
     * @inheritDoc
     */
    public function bulkinsert(
        TableMetadata $table,
        array $rows,
        ?InsertMode $mode = null,
        ?array $updateColumns = null,
        ?array $conflictColumns = null,
    ): void {
        $end = $this->startCommandTimer();
        $this->writeCommand('bulkinsert', [$table->getName()]);
        parent::bulkinsert($table, $rows, $mode, $updateColumns, $conflictColumns);
        $end();
    }

    /**
     * @inheritDoc
     */
    public function createTable(TableMetadata $table, array $columns = [], array $indexes = []): void
    {
        $end = $this->startCommandTimer();
        $this->writeCommand('createTable', [$table->getName()]);
        parent::createTable($table, $columns, $indexes);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function changePrimaryKey(TableMetadata $table, $newColumns): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('changePrimaryKey', [$table->getName()]);
        $adapter->changePrimaryKey($table, $newColumns);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function changeComment(TableMetadata $table, ?string $newComment): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('changeComment', [$table->getName()]);
        $adapter->changeComment($table, $newComment);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function renameTable(string $tableName, string $newName): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('renameTable', [$tableName, $newName]);
        $adapter->renameTable($tableName, $newName);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function dropTable(string $tableName): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('dropTable', [$tableName]);
        $adapter->dropTable($tableName);
        $end();
    }

    /**
     * @inheritDoc
     */
    public function truncateTable(string $tableName): void
    {
        $end = $this->startCommandTimer();
        $this->writeCommand('truncateTable', [$tableName]);
        parent::truncateTable($tableName);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function addColumn(TableMetadata $table, Column $column): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand(
            'addColumn',
            [
                $table->getName(),
                $column->getName(),
                $column->getType(),
            ],
        );
        $adapter->addColumn($table, $column);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function renameColumn(string $tableName, string $columnName, string $newColumnName): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('renameColumn', [$tableName, $columnName, $newColumnName]);
        $adapter->renameColumn($tableName, $columnName, $newColumnName);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function changeColumn(string $tableName, string $columnName, Column $newColumn): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('changeColumn', [$tableName, $columnName, $newColumn->getType()]);
        $adapter->changeColumn($tableName, $columnName, $newColumn);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function dropColumn(string $tableName, string $columnName): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('dropColumn', [$tableName, $columnName]);
        $adapter->dropColumn($tableName, $columnName);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function addIndex(TableMetadata $table, Index $index): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('addIndex', [$table->getName(), $index->getColumns()]);
        $adapter->addIndex($table, $index);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function dropIndex(string $tableName, $columns): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('dropIndex', [$tableName, $columns]);
        $adapter->dropIndex($tableName, $columns);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function dropIndexByName(string $tableName, string $indexName): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('dropIndexByName', [$tableName, $indexName]);
        $adapter->dropIndexByName($tableName, $indexName);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function addForeignKey(TableMetadata $table, ForeignKey $foreignKey): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('addForeignKey', [$table->getName(), $foreignKey->getColumns()]);
        $adapter->addForeignKey($table, $foreignKey);
        $end();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \BadMethodCallException
     * @return void
     */
    public function dropForeignKey(string $tableName, array $columns, ?string $constraint = null): void
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof DirectActionInterface) {
            throw new BadMethodCallException('The adapter needs to implement DirectActionInterface');
        }
        $end = $this->startCommandTimer();
        $this->writeCommand('dropForeignKey', [$tableName, $columns]);
        $adapter->dropForeignKey($tableName, $columns, $constraint);
        $end();
    }

    /**
     * @inheritDoc
     */
    public function createDatabase(string $name, array $options = []): void
    {
        $end = $this->startCommandTimer();
        $this->writeCommand('createDatabase', [$name]);
        parent::createDatabase($name, $options);
        $end();
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase(string $name): void
    {
        $end = $this->startCommandTimer();
        $this->writeCommand('dropDatabase', [$name]);
        parent::dropDatabase($name);
        $end();
    }

    /**
     * @inheritDoc
     */
    public function createSchema(string $schemaName = 'public'): void
    {
        $end = $this->startCommandTimer();
        $this->writeCommand('createSchema', [$schemaName]);
        parent::createSchema($schemaName);
        $end();
    }

    /**
     * @inheritDoc
     */
    public function dropSchema(string $schemaName): void
    {
        $end = $this->startCommandTimer();
        $this->writeCommand('dropSchema', [$schemaName]);
        parent::dropSchema($schemaName);
        $end();
    }

    /**
     * @inheritDoc
     */
    public function executeActions(TableMetadata $table, array $actions): void
    {
        $end = $this->startCommandTimer();
        $this->writeCommand(sprintf('Altering table %s', $table->getName()));
        parent::executeActions($table, $actions);
        $end();
    }
}
