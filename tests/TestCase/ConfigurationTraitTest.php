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
namespace Migrations\Test\TestCase;

use Cake\Core\BasePlugin;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\Test\ExampleCommand;
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

    /**
     * Returns the combination of the phinx driver name with
     * the associated cakephp driver instance that should be mapped to it
     *
     * @return array
     */
    public function driversProvider()
    {
        return [
            ['mysql', $this->getMockBuilder('\Cake\Database\Driver\Mysql')->getMock()],
            ['pgsql', $this->getMockBuilder('\Cake\Database\Driver\Postgres')->getMock()],
            ['sqlite', $this->getMockBuilder('\Cake\Database\Driver\Sqlite')->getMock()],
        ];
    }

    /**
     * Tests that the correct driver name is inferred from the driver
     * instance that is passed to getAdapterName()
     *
     * @dataProvider driversProvider
     * @return void
     */
    public function testGetAdapterName($expected, $cakeDriver)
    {
        $this->assertEquals(
            $expected,
            $this->command->getAdapterName($cakeDriver)
        );
    }

    /**
     * Tests that the configuration object is created out of the database configuration
     * made for the application
     *
     * @return void
     */
    public function testGetConfig()
    {
        ConnectionManager::setConfig([
            'default' => [
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
                    \PDO::MYSQL_ATTR_SSL_CA => 'flags do not overwrite config',
                    \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                ],
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
        $tmpPath = rtrim(sys_get_temp_dir(), DS) . DS;
        Plugin::getCollection()->add(new BasePlugin([
            'name' => 'MyPlugin',
            'path' => $tmpPath,
        ]));
        $input = $this->getMockBuilder(InputInterface::class)->getMock();
        $this->command->setInput($input);

        $input->expects($this->at(1))
            ->method('getOption')
            ->with('plugin')
            ->will($this->returnValue('MyPlugin'));

        $input->expects($this->at(4))
            ->method('getOption')
            ->with('plugin')
            ->will($this->returnValue('MyPlugin'));

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
        ConnectionManager::setConfig([
            'custom' => [
                'className' => 'Cake\Database\Connection',
                'driver' => 'Cake\Database\Driver\Mysql',
                'host' => 'foo.bar.baz',
                'username' => 'rooty',
                'password' => 'the_password2',
                'database' => 'the_database2',
                'encoding' => 'utf-8',
            ],
        ]);

        $input = $this->getMockBuilder(InputInterface::class)->getMock();
        $this->command->setInput($input);

        $input->expects($this->at(5))
            ->method('getOption')
            ->with('connection')
            ->will($this->returnValue('custom'));

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
}
