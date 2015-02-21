<?php
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
namespace Migrations\Test;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\ConfigurationTrait;

/**
 * Tests the ConfigurationTrait
 */
class ConfigurationTraitTest extends TestCase
{

    /**
     * Setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->command = new ExampleCommand;
    }

    /**
     * Returns the combination of the phinx driver name with
     * the associated cakephp driver instance that should be mapped to it
     *
     * @return void
     */
    public function driversProvider()
    {
        return [
            ['mysql', $this->getMock('Cake\Database\Driver\Mysql')],
            ['pgsql', $this->getMock('Cake\Database\Driver\Postgres')],
            ['sqlite', $this->getMock('Cake\Database\Driver\Sqlite')]
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
        ConnectionManager::config([
            'default' => [
                'className' => 'Cake\Database\Connection',
                'driver' => 'Cake\Database\Driver\Mysql',
                'host' => 'foo.bar',
                'username' => 'root',
                'password' => 'the_password',
                'database' => 'the_database',
                'encoding' => 'utf-8'
            ]
        ]);

        $input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $this->command->setInput($input);
        $config = $this->command->getConfig();
        $this->assertInstanceOf('Phinx\Config\Config', $config);

        $expected = ROOT . DS . 'config' . DS . 'Migrations';
        $this->assertEquals($expected, $config->getMigrationPath());

        $this->assertEquals(
            'phinxlog',
            $config['environments']['default_migration_table']
        );

        $environment = $config['environments']['default'];
        $this->assertEquals('mysql', $environment['adapter']);
        $this->assertEquals('foo.bar', $environment['host']);

        $this->assertEquals('root', $environment['user']);
        $this->assertEquals('the_password', $environment['pass']);
        $this->assertEquals('the_database', $environment['name']);
        $this->assertEquals('utf-8', $environment['charset']);
    }

    /**
     * Tests that another phinxlog table is used when passing the plugin option in the input
     *
     * @return void
     */
    public function testGetConfigWithPlugin()
    {
        $tmpPath = rtrim(sys_get_temp_dir(), DS) . DS;
        Plugin::load('MyPlugin', ['path' => $tmpPath]);
        $input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $this->command->setInput($input);

        $input->expects($this->at(1))
            ->method('getOption')
            ->with('plugin')
            ->will($this->returnValue('MyPlugin'));

        $input->expects($this->at(2))
            ->method('getOption')
            ->with('plugin')
            ->will($this->returnValue('MyPlugin'));

        $config = $this->command->getConfig();
        $this->assertInstanceOf('Phinx\Config\Config', $config);

        $this->assertEquals(
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
        ConnectionManager::config([
            'custom' => [
                'className' => 'Cake\Database\Connection',
                'driver' => 'Cake\Database\Driver\Mysql',
                'host' => 'foo.bar.baz',
                'username' => 'rooty',
                'password' => 'the_password2',
                'database' => 'the_database2',
                'encoding' => 'utf-8'
            ]
        ]);

        $input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $this->command->setInput($input);

        $input->expects($this->at(2))
            ->method('getOption')
            ->with('connection')
            ->will($this->returnValue('custom'));

        $input->expects($this->at(3))
            ->method('getOption')
            ->with('connection')
            ->will($this->returnValue('custom'));

        $config = $this->command->getConfig();
        $this->assertInstanceOf('Phinx\Config\Config', $config);

        $expected = ROOT . DS . 'config' . DS . 'Migrations';
        $this->assertEquals($expected, $config->getMigrationPath());

        $this->assertEquals(
            'phinxlog',
            $config['environments']['default_migration_table']
        );

        $environment = $config['environments']['default'];
        $this->assertEquals('mysql', $environment['adapter']);
        $this->assertEquals('foo.bar.baz', $environment['host']);

        $this->assertEquals('rooty', $environment['user']);
        $this->assertEquals('the_password2', $environment['pass']);
        $this->assertEquals('the_database2', $environment['name']);
        $this->assertEquals('utf-8', $environment['charset']);
    }
}
