<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\TestSuite;

use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Cake\TestSuite\ConnectionHelper;
use Migrations\Migrations;
use RuntimeException;

class Migrator
{
    /**
     * @var \Cake\TestSuite\ConnectionHelper
     */
    protected $helper;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->helper = new ConnectionHelper();
    }

    /**
     * Runs migrations.
     *
     * For options, {@see \Migrations\Migrations::migrate()}.
     *
     * @param array $options Migrate options
     * @param bool $truncateTables Truncate all tables after running migrations. Defaults to true.
     * @return void
     */
    public function run(
        array $options = [],
        bool $truncateTables = true
    ): void {
        // Don't recreate schema if we are in a phpunit separate process test.
        if (isset($GLOBALS['__PHPUNIT_BOOTSTRAP'])) {
            return;
        }

        $options += ['connection' => 'test'];
        $migrations = new Migrations();

        if ($this->shouldDropTables($migrations, $options)) {
            $this->helper->dropTables($options['connection']);
        }

        if (!$migrations->migrate($options)) {
            throw new RuntimeException(sprintf('Unable to migrate fixtures for `%s`.', $options['connection']));
        }

        if ($truncateTables) {
            $tables = ConnectionManager::get($options['connection'])->getSchemaCollection()->listTables();
            $tables = array_filter($tables, function ($table) {
                return strpos($table, 'phinxlog') === false;
            });
            $this->helper->truncateTables($options['connection'], $tables);
        }
    }

    /**
     * Detect if migrations have changed and the database needs to be wiped.
     *
     * @param \Migrations\Migrations $migrations The migrations service.
     * @param array $options The connection options.
     * @return bool
     */
    protected function shouldDropTables(Migrations $migrations, array $options): bool
    {
        Log::write('debug', "Reading migrations status for {$options['connection']}...");

        foreach ($migrations->status($options) as $migration) {
            if ($migration['status'] === 'up' && ($migration['missing'] ?? false)) {
                Log::write('debug', 'Missing migration(s) detected.');

                return true;
            }
            if ($migration['status'] === 'down') {
                Log::write('debug', 'New migration(s) found.');

                return true;
            }
        }
        Log::write('debug', 'No migration changes detected');

        return false;
    }
}
