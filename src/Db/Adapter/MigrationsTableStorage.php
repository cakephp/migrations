<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Db\Adapter;

use Cake\Database\Query\SelectQuery;
use Exception;
use InvalidArgumentException;
use Migrations\Db\Table;
use Migrations\MigrationInterface;

/**
 * Backwards compatibility layer for migration table storage.
 *
 * Defaults to `phinxlog` and each plugin has its own table
 * that is controlled via parameters.
 */
class MigrationsTableStorage
{
    /**
     * Constructor
     *
     * @param \Migrations\Db\Adapter\AbstractAdapter $adapter The database adapter.
     * @param string $schemaTableName The schema table name.
     */
    public function __construct(
        protected AbstractAdapter $adapter,
        protected string $schemaTableName = 'phinxlog',
    ) {
    }

    /**
     * Gets the schema table name.
     *
     * @return string
     */
    public function getSchemaTableName(): string
    {
        return $this->schemaTableName;
    }

    /**
     * Gets all the migration versions.
     *
     * @param array<string, string> $orderBy The order by clause.
     * @return \Cake\Database\Query\SelectQuery
     */
    public function getVersions(array $orderBy): SelectQuery
    {
        $query = $this->adapter->getSelectBuilder();
        $query->select('*')
            ->from($this->getSchemaTableName())
            ->orderBy($orderBy);

        return $query;
    }

    /**
     * Records that a migration was run in the database.
     *
     * @param \Migrations\MigrationInterface $migration Migration
     * @param string $startTime Start time
     * @param string $endTime End time
     * @return void
     */
    public function recordUp(MigrationInterface $migration, string $startTime, string $endTime): void
    {
        $query = $this->adapter->getInsertBuilder();
        $query->insert(['version', 'migration_name', 'start_time', 'end_time', 'breakpoint'])
            ->into($this->getSchemaTableName())
            ->values([
                'version' => (string)$migration->getVersion(),
                'migration_name' => substr($migration->getName(), 0, 100),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'breakpoint' => 0,
            ]);
        $this->adapter->executeQuery($query);
    }

    /**
     * Removes the record of a migration having been run.
     *
     * @param \Migrations\MigrationInterface $migration Migration
     * @return void
     */
    public function recordDown(MigrationInterface $migration): void
    {
        $query = $this->adapter->getDeleteBuilder();
        $query->delete()
            ->from($this->getSchemaTableName())
            ->where(['version' => (string)$migration->getVersion()]);
        $this->adapter->executeQuery($query);
    }

    /**
     * Toggles the breakpoint state of a migration.
     *
     * @param \Migrations\MigrationInterface $migration Migration
     * @return void
     */
    public function toggleBreakpoint(MigrationInterface $migration): void
    {
        $params = [
            $migration->getVersion(),
        ];
        $this->adapter->query(
            sprintf(
                'UPDATE %1$s SET %2$s = CASE %2$s WHEN true THEN false ELSE true END, %4$s = %4$s WHERE %3$s = ?;',
                $this->adapter->quoteTableName($this->getSchemaTableName()),
                $this->adapter->quoteColumnName('breakpoint'),
                $this->adapter->quoteColumnName('version'),
                $this->adapter->quoteColumnName('start_time'),
            ),
            $params,
        );
    }

    /**
     * Resets all breakpoints.
     *
     * @return int The number of affected rows.
     */
    public function resetAllBreakpoints(): int
    {
        $query = $this->adapter->getUpdateBuilder();
        $query->update($this->getSchemaTableName())
            ->set([
                'breakpoint' => 0,
                'start_time' => $query->identifier('start_time'),
            ])
            ->where([
                'breakpoint !=' => 0,
            ]);

        return $this->adapter->executeQuery($query);
    }

    /**
     * Marks a migration as a breakpoint or not depending on $state.
     *
     * @param \Migrations\MigrationInterface $migration Migration
     * @param bool $state The breakpoint state to set.
     * @return void
     */
    public function markBreakpoint(MigrationInterface $migration, bool $state): void
    {
        $query = $this->adapter->getUpdateBuilder();
        $query->update($this->getSchemaTableName())
            ->set([
                'breakpoint' => (int)$state,
                'start_time' => $query->identifier('start_time'),
            ])
            ->where([
                'version' => $migration->getVersion(),
            ]);
        $this->adapter->executeQuery($query);
    }

    /**
     * Creates the migration storage table
     *
     * @return void
     * @throws \InvalidArgumentException When there is a problem creating the table.
     */
    public function createTable(): void
    {
        try {
            $options = [
                'id' => false,
                'primary_key' => 'version',
            ];

            $table = new Table($this->getSchemaTableName(), $options, $this->adapter);
            $table->addColumn('version', 'biginteger', ['null' => false])
                ->addColumn('migration_name', 'string', ['limit' => 100, 'default' => null, 'null' => true])
                ->addColumn('start_time', 'timestamp', ['default' => null, 'null' => true])
                ->addColumn('end_time', 'timestamp', ['default' => null, 'null' => true])
                ->addColumn('breakpoint', 'boolean', ['default' => false, 'null' => false])
                ->save();
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                'There was a problem creating the schema table: ' . $exception->getMessage(),
                (int)$exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * Upgrades the migration storage table
     *
     * @return void
     */
    public function upgradeTable(): void
    {
        $table = new Table($this->getSchemaTableName(), [], $this->adapter);
        if (!$table->hasColumn('migration_name')) {
            $table
                ->addColumn(
                    'migration_name',
                    'string',
                    ['limit' => 100, 'after' => 'version', 'default' => null, 'null' => true],
                )
                ->save();
        }
        if (!$table->hasColumn('breakpoint')) {
            $table
                ->addColumn('breakpoint', 'boolean', ['default' => false, 'null' => false])
                ->save();
        }
    }
}
