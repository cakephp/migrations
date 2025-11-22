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
 * Unified migration table storage.
 *
 * Uses a single `cake_migrations` table with a `plugin` column
 * to track all migrations (app and plugins) in one place.
 */
class UnifiedMigrationsTableStorage
{
    /**
     * The table name for unified migrations storage.
     */
    public const TABLE_NAME = 'cake_migrations';

    /**
     * Constructor
     *
     * @param \Migrations\Db\Adapter\AbstractAdapter $adapter The database adapter.
     * @param string|null $plugin The plugin name (null for app migrations).
     */
    public function __construct(
        protected AbstractAdapter $adapter,
        protected ?string $plugin = null,
    ) {
    }

    /**
     * Gets all the migration versions for the current plugin context.
     *
     * @param array<string, string> $orderBy The order by clause.
     * @return \Cake\Database\Query\SelectQuery
     */
    public function getVersions(array $orderBy): SelectQuery
    {
        $query = $this->adapter->getSelectBuilder();
        $query->select('*')
            ->from(self::TABLE_NAME)
            ->orderBy($orderBy);

        if ($this->plugin === null) {
            $query->where(['plugin IS' => null]);
        } else {
            $query->where(['plugin' => $this->plugin]);
        }

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
        $query->insert(['version', 'migration_name', 'plugin', 'start_time', 'end_time', 'breakpoint'])
            ->into(self::TABLE_NAME)
            ->values([
                'version' => (string)$migration->getVersion(),
                'migration_name' => substr($migration->getName(), 0, 100),
                'plugin' => $this->plugin,
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
            ->from(self::TABLE_NAME);

        if ($this->plugin === null) {
            $query->where([
                'version' => (string)$migration->getVersion(),
                'plugin IS' => null,
            ]);
        } else {
            $query->where([
                'version' => (string)$migration->getVersion(),
                'plugin' => $this->plugin,
            ]);
        }

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
        $pluginCondition = $this->plugin === null
            ? sprintf('%s IS NULL', $this->adapter->quoteColumnName('plugin'))
            : sprintf('%s = ?', $this->adapter->quoteColumnName('plugin'));

        $params = $this->plugin === null
            ? [$migration->getVersion()]
            : [$migration->getVersion(), $this->plugin];

        $this->adapter->query(
            sprintf(
                'UPDATE %1$s SET %2$s = CASE %2$s WHEN true THEN false ELSE true END, %4$s = %4$s WHERE %3$s = ? AND %5$s;',
                $this->adapter->quoteTableName(self::TABLE_NAME),
                $this->adapter->quoteColumnName('breakpoint'),
                $this->adapter->quoteColumnName('version'),
                $this->adapter->quoteColumnName('start_time'),
                $pluginCondition,
            ),
            $params,
        );
    }

    /**
     * Resets all breakpoints for the current plugin context.
     *
     * @return int The number of affected rows.
     */
    public function resetAllBreakpoints(): int
    {
        $query = $this->adapter->getUpdateBuilder();
        $query->update(self::TABLE_NAME)
            ->set([
                'breakpoint' => 0,
                'start_time' => $query->identifier('start_time'),
            ]);

        if ($this->plugin === null) {
            $query->where([
                'breakpoint !=' => 0,
                'plugin IS' => null,
            ]);
        } else {
            $query->where([
                'breakpoint !=' => 0,
                'plugin' => $this->plugin,
            ]);
        }

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
        $query->update(self::TABLE_NAME)
            ->set([
                'breakpoint' => (int)$state,
                'start_time' => $query->identifier('start_time'),
            ]);

        if ($this->plugin === null) {
            $query->where([
                'version' => $migration->getVersion(),
                'plugin IS' => null,
            ]);
        } else {
            $query->where([
                'version' => $migration->getVersion(),
                'plugin' => $this->plugin,
            ]);
        }

        $this->adapter->executeQuery($query);
    }

    /**
     * Creates the unified migration storage table.
     *
     * @return void
     * @throws \InvalidArgumentException When there is a problem creating the table.
     */
    public function createTable(): void
    {
        try {
            $options = [
                'id' => true,
                'primary_key' => 'id',
            ];

            $table = new Table(self::TABLE_NAME, $options, $this->adapter);
            $table->addColumn('version', 'biginteger', ['null' => false])
                ->addColumn('migration_name', 'string', ['limit' => 100, 'default' => null, 'null' => true])
                ->addColumn('plugin', 'string', ['limit' => 100, 'default' => null, 'null' => true])
                ->addColumn('start_time', 'timestamp', ['default' => null, 'null' => true])
                ->addColumn('end_time', 'timestamp', ['default' => null, 'null' => true])
                ->addColumn('breakpoint', 'boolean', ['default' => false, 'null' => false])
                ->addIndex(['version', 'plugin'], ['unique' => true, 'name' => 'version_plugin_unique'])
                ->save();
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                'There was a problem creating the migrations table: ' . $exception->getMessage(),
                (int)$exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * Upgrades the migration storage table if needed.
     *
     * @return void
     */
    public function upgradeTable(): void
    {
        $table = new Table(self::TABLE_NAME, [], $this->adapter);

        // Add plugin column if missing (upgrade from old unified table without plugin)
        if (!$table->hasColumn('plugin')) {
            $table
                ->addColumn(
                    'plugin',
                    'string',
                    ['limit' => 100, 'after' => 'migration_name', 'default' => null, 'null' => true],
                )
                ->save();
        }

        // Ensure unique index exists
        if (!$table->hasIndex(['version', 'plugin'])) {
            $table
                ->addIndex(['version', 'plugin'], ['unique' => true, 'name' => 'version_plugin_unique'])
                ->save();
        }
    }
}
