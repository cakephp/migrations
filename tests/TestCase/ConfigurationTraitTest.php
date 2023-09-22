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
namespace Migrations\Test\TestCase;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\Test\ExampleCommand;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests the ConfigurationTrait
 */
class ConfigurationTraitTest extends TestCase
{
    /**
     * @var \Migrations\Test\ExampleCommand
     */
    protected $command;

    /**
     * Setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->command = new ExampleCommand();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        ConnectionManager::drop('custom');
        ConnectionManager::drop('default');
    }

    /**
     * Tests that the correct driver name is inferred from the driver
     * instance that is passed to getAdapterName()
     *
     * @return void
     */
    public function testGetAdapterName()
    {
        $this->assertEquals('mysql', $this->command->getAdapterName('\Cake\Database\Driver\Mysql'));
        $this->assertEquals('pgsql', $this->command->getAdapterName('\Cake\Database\Driver\Postgres'));
        $this->assertEquals('sqlite', $this->command->getAdapterName('\Cake\Database\Driver\Sqlite'));
    }

    /**
     * Tests that the configuration object is created out of the database configuration
     * made for the application
     *
     * @return void
     */
    public function testGetConfig()
    {
        ConnectionManager::setConfig('default', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Mysql',
            'host' => 'foo.bar',
            'username' => 'root',
            'password' => 'the_password',
            'database' => 'the_database',
            'encoding' => 'utf-8',
            'ssl_ca' => '/certs/my_cert',
            'ssl_key' => 'ssl_key_value',
            'ssl_cert' => 'ssl_cert_value',
            'flags' => [
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::MYSQL_ATTR_SSL_CA => 'flags do not overwrite config',
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ],
        ]);

        /** @var \Symfony\Component\Console\Input\InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->getMockBuilder(InputInterface::class)->getMock();
        $this->command->setInput($input);
        $config = $this->command->getConfig();
        $this->assertInstanceOf('Phinx\Config\Config', $config);

        $expected = ROOT . DS . 'config' . DS . 'Migrations';
        $migrationPaths = $config->getMigrationPaths();
        $this->assertSame($expected, array_pop($migrationPaths));

        $this->assertSame(
            'phinxlog',
            $config['environments']['default_migration_table']
        );

        $environment = $config['environments']['default'];
        $this->assertSame('mysql', $environment['adapter']);
        $this->assertSame('foo.bar', $environment['host']);

        $this->assertSame('root', $environment['user']);
        $this->assertSame('the_password', $environment['pass']);
        $this->assertSame('the_database', $environment['name']);
        $this->assertSame('utf-8', $environment['charset']);
        $this->assertSame('/certs/my_cert', $environment['mysql_attr_ssl_ca']);
        $this->assertSame('ssl_key_value', $environment['mysql_attr_ssl_key']);
        $this->assertSame('ssl_cert_value', $environment['mysql_attr_ssl_cert']);
        $this->assertFalse($environment['mysql_attr_ssl_verify_server_cert']);
        $this->assertTrue($environment['attr_emulate_prepares']);
        $this->assertSame([], $environment['dsn_options']);
    }

    public function testGetConfigWithDsnOptions()
    {
        ConnectionManager::setConfig('default', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Sqlserver',
            'database' => 'the_database',
            // DSN options
            'connectionPooling' => true,
            'failoverPartner' => 'Partner',
            'loginTimeout' => 123,
            'multiSubnetFailover' => true,
            'encrypt' => true,
            'trustServerCertificate' => true,
        ]);

        /** @var \Symfony\Component\Console\Input\InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->getMockBuilder(InputInterface::class)->getMock();
        $this->command->setInput($input);
        $config = $this->command->getConfig();
        $this->assertInstanceOf('Phinx\Config\Config', $config);

        $environment = $config['environments']['default'];
        $this->assertSame('sqlsrv', $environment['adapter']);
        $this->assertSame(
            [
                'ConnectionPooling' => true,
                'Failover_Partner' => 'Partner',
                'LoginTimeout' => 123,
                'MultiSubnetFailover' => true,
                'Encrypt' => true,
                'TrustServerCertificate' => true,
            ],
            $environment['dsn_options']
        );
    }

    /**
     * Tests that the when the Adapter is built, the Connection cache metadata
     * feature is turned off to prevent "unknown column" errors when adding a column
     * then adding data to that column
     *
     * @return void
     */
    public function testCacheMetadataDisabled()
    {
        $input = new ArrayInput([], $this->command->getDefinition());
        /** @var \Symfony\Component\Console\Output\OutputInterface|\PHPUnit\Framework\MockObject\MockObject $output */
        $output = $this->getMockBuilder(OutputInterface::class)->getMock();
        $this->command->setInput($input);

        $input->setOption('connection', 'test');
        $this->command->bootstrap($input, $output);
        $config = ConnectionManager::get('test')->config();
        $this->assertFalse($config['cacheMetadata']);
    }

    /**
     * Tests that another phinxlog table is used when passing the plugin option in the input
     *
     * @return void
     */
    public function testGetConfigWithPlugin()
    {
        ConnectionManager::setConfig('default', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Mysql',
            'database' => 'the_database',
        ]);

        $tmpPath = rtrim(sys_get_temp_dir(), DS) . DS;
        Plugin::getCollection()->add(new BasePlugin([
            'name' => 'MyPlugin',
            'path' => $tmpPath,
        ]));
        $input = new ArrayInput([], $this->command->getDefinition());
        $this->command->setInput($input);

        $input->setOption('plugin', 'MyPlugin');

        $config = $this->command->getConfig();
        $this->assertInstanceOf('Phinx\Config\Config', $config);

        $this->assertSame(
            'my_plugin_phinxlog',
            $config['environments']['default_migration_table']
        );
    }

    /**
     * Tests that passing a connection option in the input will configure the environment
     * to use that connection
     *
     * @return void
     */
    public function testGetConfigWithConnectionName()
    {
        ConnectionManager::setConfig('custom', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Mysql',
            'host' => 'foo.bar.baz',
            'username' => 'rooty',
            'password' => 'the_password2',
            'database' => 'the_database2',
            'encoding' => 'utf-8',
        ]);

        $input = new ArrayInput([], $this->command->getDefinition());
        $this->command->setInput($input);

        $input->setOption('connection', 'custom');

        $config = $this->command->getConfig();
        $this->assertInstanceOf('Phinx\Config\Config', $config);

        $expected = ROOT . DS . 'config' . DS . 'Migrations';
        $migrationPaths = $config->getMigrationPaths();
        $this->assertSame($expected, array_pop($migrationPaths));

        $this->assertSame(
            'phinxlog',
            $config['environments']['default_migration_table']
        );

        $environment = $config['environments']['default'];
        $this->assertSame('mysql', $environment['adapter']);
        $this->assertSame('foo.bar.baz', $environment['host']);

        $this->assertSame('rooty', $environment['user']);
        $this->assertSame('the_password2', $environment['pass']);
        $this->assertSame('the_database2', $environment['name']);
        $this->assertSame('utf-8', $environment['charset']);
    }

    /**
     * Generates Command mock to override getOperationsPath return value
     *
     * @param string $migrationsPath
     * @param string $seedsPath
     * @return ExampleCommand
     */
    protected function _getCommandMock(string $migrationsPath, string $seedsPath): ExampleCommand
    {
        $command = $this
            ->getMockBuilder(ExampleCommand::class)
            ->onlyMethods(['getOperationsPath'])
            ->getMock();
        /** @var \Symfony\Component\Console\Input\InputInterface|\PHPUnit\Framework\MockObject\MockObject $input */
        $input = $this->getMockBuilder(InputInterface::class)->getMock();
        $command->setInput($input);
        $command->expects($this->any())
            ->method('getOperationsPath')
            ->will(
                $this->returnValueMap([
                    [$input, 'Migrations', $migrationsPath],
                    [$input, 'Seeds', $seedsPath],
                ])
            );

        return $command;
    }

    /**
     * Test getConfig, migrations path does not exist, debug is disabled
     *
     * @return void
     */
    public function testGetConfigNoMigrationsFolderDebugDisabled()
    {
        ConnectionManager::setConfig('default', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Mysql',
            'host' => 'foo.bar',
            'username' => 'root',
            'password' => 'the_password',
            'database' => 'the_database',
            'encoding' => 'utf-8',
        ]);
        Configure::write('debug', false);
        $migrationsPath = ROOT . DS . 'config' . DS . 'TestGetConfigMigrations';
        $seedsPath = ROOT . DS . 'config' . DS . 'TestGetConfigSeeds';

        $command = $this->_getCommandMock($migrationsPath, $seedsPath);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Migrations path `%s` does not exist and cannot be created because `debug` is disabled.',
            $migrationsPath
        ));
        $command->getConfig();
    }

    /**
     * Test getConfig, migrations path does exist but seeds path does not, debug is disabled
     *
     * @return void
     */
    public function testGetConfigNoSeedsFolderDebugDisabled()
    {
        ConnectionManager::setConfig('default', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Mysql',
            'host' => 'foo.bar',
            'username' => 'root',
            'password' => 'the_password',
            'database' => 'the_database',
            'encoding' => 'utf-8',
        ]);
        Configure::write('debug', false);

        $migrationsPath = ROOT . DS . 'config' . DS . 'TestGetConfigMigrations';
        mkdir($migrationsPath, 0777, true);
        $seedsPath = ROOT . DS . 'config' . DS . 'TestGetConfigSeeds';

        $command = $this->_getCommandMock($migrationsPath, $seedsPath);
        $this->assertFalse(is_dir($seedsPath));
        try {
            $command->getConfig();
        } finally {
            rmdir($migrationsPath);
        }
    }

    /**
     * Test getConfig, migrations and seeds paths do not exist, debug is enabled
     *
     * @return void
     */
    public function testGetConfigNoMigrationsOrSeedsFolderDebugEnabled()
    {
        ConnectionManager::setConfig('default', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Mysql',
            'host' => 'foo.bar',
            'username' => 'root',
            'password' => 'the_password',
            'database' => 'the_database',
            'encoding' => 'utf-8',
        ]);
        $migrationsPath = ROOT . DS . 'config' . DS . 'TestGetConfigMigrations';
        $seedsPath = ROOT . DS . 'config' . DS . 'TestGetConfigSeeds';
        mkdir($migrationsPath, 0777, true);
        mkdir($seedsPath, 0777, true);

        $command = $this->_getCommandMock($migrationsPath, $seedsPath);

        $command->getConfig();

        $this->assertTrue(is_dir($migrationsPath));
        $this->assertTrue(is_dir($seedsPath));

        rmdir($migrationsPath);
        rmdir($seedsPath);
    }
}
