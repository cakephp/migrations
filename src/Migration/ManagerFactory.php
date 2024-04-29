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
namespace Migrations\Migration;

use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use Migrations\Config\Config;
use Migrations\Config\ConfigInterface;
use RuntimeException;

/**
 * Factory for Config and Manager
 *
 * Used by Console commands.
 *
 * @internal
 */
class ManagerFactory
{
    /**
     * Constructor
     *
     * ## Options
     *
     * - source - The directory in app/config that migrations and seeds should be read from.
     * - plugin - The plugin name that migrations are being run on.
     * - connection - The connection name.
     * - dry-run - Whether or not dry-run mode should be enabled.
     *
     * @param array $options The command line options for creating config/manager.
     */
    public function __construct(protected array $options)
    {
    }

    /**
     * Read configuration options used for this factory
     *
     * @param string $name The option name to read
     * @return mixed Option value or null
     */
    public function getOption(string $name): mixed
    {
        if (!isset($this->options[$name])) {
            return null;
        }

        return $this->options[$name];
    }

    /**
     * Create a ConfigInterface instance based on the factory options.
     *
     * @return \Migrations\Config\ConfigInterface
     */
    public function createConfig(): ConfigInterface
    {
        $folder = (string)$this->getOption('source');

        // Get the filepath for migrations and seeds.
        // We rely on factory parameters to define which directory to use.
        $dir = ROOT . DS . 'config' . DS . $folder;
        if (defined('CONFIG')) {
            $dir = CONFIG . $folder;
        }
        $plugin = $this->getOption('plugin');
        if ($plugin && is_string($plugin)) {
            $dir = Plugin::path($plugin) . 'config' . DS . $folder;
        }

        // Get the phinxlog table name. Plugins have separate migration history.
        // The names and separate table history is something we could change in the future.
        $table = 'phinxlog';
        if ($plugin && is_string($plugin)) {
            $prefix = Inflector::underscore($plugin) . '_';
            $prefix = str_replace(['\\', '/', '.'], '_', $prefix);
            $table = $prefix . $table;
        }
        $templatePath = dirname(__DIR__) . DS . 'templates' . DS;
        $connectionName = (string)$this->getOption('connection');

        $connectionConfig = ConnectionManager::getConfig($connectionName);
        if (!$connectionConfig) {
            throw new RuntimeException("Could not find connection `{$connectionName}`");
        }

        /** @var array<string, string> $connectionConfig */
        $adapter = $connectionConfig['scheme'] ?? null;
        $adapterConfig = [
            'adapter' => $adapter,
            'connection' => $connectionName,
            'database' => $connectionConfig['database'],
            'migration_table' => $table,
            'dryrun' => $this->getOption('dry-run'),
        ];

        $configData = [
            'paths' => [
                'migrations' => $dir,
                'seeds' => $dir,
            ],
            'templates' => [
                'file' => $templatePath . 'Phinx/create.php.template',
            ],
            'migration_base_class' => 'Migrations\AbstractMigration',
            'environment' => $adapterConfig,
            'plugin' => $plugin,
            'source' => (string)$this->getOption('source'),
            'feature_flags' => [
                'unsigned_primary_keys' => Configure::read('Migrations.unsigned_primary_keys'),
                'column_null_default' => Configure::read('Migrations.column_null_default'),
            ],
        ];

        return new Config($configData);
    }

    /**
     * Get the migration manager for the current CLI options and application configuration.
     *
     * @param \Cake\Console\ConsoleIo $io The command io.
     * @param \Migrations\Config\ConfigInterface $config A config instance. Providing null will create a new Config
     * based on the factory constructor options.
     * @return \Migrations\Migration\Manager
     */
    public function createManager(ConsoleIo $io, ?ConfigInterface $config = null): Manager
    {
        $config ??= $this->createConfig();

        return new Manager($config, $io);
    }
}
