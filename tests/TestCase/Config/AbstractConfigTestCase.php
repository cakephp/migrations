<?php

namespace Migrations\Test\TestCase\Config;

use Cake\Datasource\ConnectionManager;
use PHPUnit\Framework\TestCase;

/**
 * Class AbstractConfigTest
 *
 * @coversNothing
 */
abstract class AbstractConfigTestCase extends TestCase
{
    /**
     * @var string
     */
    protected $migrationPath;

    /**
     * @var string
     */
    protected $seedPath;

    /**
     * Returns a sample configuration array for use with the unit tests.
     *
     * @return array
     */
    public function getConfigArray()
    {
        /** @var array<string, string> $connectionConfig */
        $connectionConfig = ConnectionManager::getConfig('test');
        $adapter = [
            'migration_table' => 'phinxlog',
            'adapter' => $connectionConfig['scheme'],
            'user' => $connectionConfig['username'] ?? '',
            'pass' => $connectionConfig['password'] ?? '',
            'host' => $connectionConfig['host'] ?? '',
            'name' => $connectionConfig['database'],
        ];

        return [
            'default' => [
                'paths' => [
                    'migrations' => '%%PHINX_CONFIG_PATH%%/testmigrations2',
                    'seeds' => '%%PHINX_CONFIG_PATH%%/db/seeds',
                ],
            ],
            'paths' => [
                'migrations' => $this->getMigrationPaths(),
                'seeds' => $this->getSeedPaths(),
            ],
            'templates' => [
                'file' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.txt',
                'class' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.php',
            ],
            // TODO ideally we only need the connection and migration table name.
            'environment' => $adapter,
        ];
    }

    public function getMigrationsConfigArray(): array
    {
        /** @var array<string, string> $connectionConfig */
        $connectionConfig = ConnectionManager::getConfig('test');
        $adapter = [
            'migration_table' => 'phinxlog',
            'adapter' => $connectionConfig['scheme'],
            'user' => $connectionConfig['username'],
            'pass' => $connectionConfig['password'],
            'host' => $connectionConfig['host'],
            'name' => $connectionConfig['database'],
        ];

        return [
            'paths' => [
                'migrations' => $this->getMigrationPaths(),
                'seeds' => $this->getSeedPaths(),
            ],
            'environment' => $adapter,
        ];
    }

    /**
     * Generate dummy migration paths
     *
     * @return string[]
     */
    protected function getMigrationPaths()
    {
        if ($this->migrationPath === null) {
            $this->migrationPath = uniqid('phinx', true);
        }

        return [$this->migrationPath];
    }

    /**
     * Generate dummy seed paths
     *
     * @return string[]
     */
    protected function getSeedPaths()
    {
        if ($this->seedPath === null) {
            $this->seedPath = uniqid('phinx', true);
        }

        return [$this->seedPath];
    }
}
