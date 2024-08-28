<?php

namespace Migrations\Test\TestCase\Config;

use Cake\Datasource\ConnectionManager;
use PHPUnit\Framework\TestCase;

/**
 * Class AbstractConfigTest
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
                'migrations' => $this->getMigrationPath(),
                'seeds' => $this->getSeedPath(),
            ],
            'templates' => [
                'file' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.txt',
                'class' => '%%PHINX_CONFIG_PATH%%/tpl/testtemplate.php',
            ],
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
                'migrations' => $this->getMigrationPath(),
                'seeds' => $this->getSeedPath(),
            ],
            'environment' => $adapter,
        ];
    }

    /**
     * Generate dummy migration paths
     *
     * @return string
     */
    protected function getMigrationPath(): string
    {
        if ($this->migrationPath === null) {
            $this->migrationPath = uniqid('phinx', true);
        }

        return $this->migrationPath;
    }

    /**
     * Generate dummy seed paths
     *
     * @return string
     */
    protected function getSeedPath(): string
    {
        if ($this->seedPath === null) {
            $this->seedPath = uniqid('phinx', true);
        }

        return $this->seedPath;
    }
}
