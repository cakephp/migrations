<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Util;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Utility\Inflector;
use Migrations\Db\Adapter\UnifiedMigrationsTableStorage;

/**
 * Trait gathering useful methods needed in various places of the plugin
 */
trait UtilTrait
{
    /**
     * Get the migrations table name used to store migrations data.
     *
     * In v5.0+, this returns either:
     * - 'cake_migrations' (unified table) for new installations
     * - Legacy phinxlog table names for existing installations with phinxlog tables
     *
     * The behavior is controlled by `Migrations.legacyTables` config:
     * - null (default): Autodetect - use legacy if phinxlog tables exist
     * - false: Always use new cake_migrations table
     * - true: Always use legacy phinxlog tables
     *
     * @param string|null $plugin Plugin name
     * @param \Cake\Database\Connection|null $connection Database connection for autodetect
     * @return string
     */
    protected function getPhinxTable(?string $plugin = null, ?Connection $connection = null): string
    {
        $config = Configure::read('Migrations.legacyTables');

        // Explicit configuration takes precedence
        if ($config === false) {
            return UnifiedMigrationsTableStorage::TABLE_NAME;
        }

        if ($config === true) {
            return $this->getLegacyTableName($plugin);
        }

        // Autodetect mode (config is null or not set)
        if ($connection !== null && $this->detectLegacyTables($connection)) {
            return $this->getLegacyTableName($plugin);
        }

        // No legacy tables detected or no connection provided - use new table
        return UnifiedMigrationsTableStorage::TABLE_NAME;
    }

    /**
     * Get the legacy phinxlog table name.
     *
     * @param string|null $plugin Plugin name
     * @return string
     */
    protected function getLegacyTableName(?string $plugin = null): string
    {
        $table = 'phinxlog';

        if (!$plugin) {
            return $table;
        }

        $plugin = Inflector::underscore($plugin) . '_';
        $plugin = str_replace(['\\', '/', '.'], '_', $plugin);

        return $plugin . $table;
    }

    /**
     * Detect if any legacy phinxlog tables exist in the database.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @return bool True if legacy tables exist
     */
    protected function detectLegacyTables(Connection $connection): bool
    {
        $dialect = $connection->getDriver()->schemaDialect();

        return $dialect->hasTable('phinxlog');
    }

    /**
     * Check if the system is using legacy migration tables.
     *
     * @param \Cake\Database\Connection|null $connection Database connection for autodetect
     * @return bool
     */
    protected function isUsingLegacyTables(?Connection $connection = null): bool
    {
        $config = Configure::read('Migrations.legacyTables');

        if ($config === false) {
            return false;
        }

        if ($config === true) {
            return true;
        }

        // Autodetect
        if ($connection !== null) {
            return $this->detectLegacyTables($connection);
        }

        return false;
    }
}
