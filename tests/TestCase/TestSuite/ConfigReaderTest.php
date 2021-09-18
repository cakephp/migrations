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
namespace Migrations\Test\TestCase\TestSuite;

use Cake\TestSuite\TestCase;
use Migrations\Test\MigratorTestTrait;
use Migrations\TestSuite\ConfigReader;

class ConfigReaderTest extends TestCase
{
    use MigratorTestTrait;

    /**
     * @var ConfigReader
     */
    public $ConfigReader;

    public function setUp(): void
    {
        parent::setUp();

        $this->ConfigReader = new ConfigReader();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->ConfigReader);
    }

    public function testSetConfigFromInjection(): void
    {
        $config = [
            ['connection' => 'Foo', 'plugin' => 'Bar',],
            ['plugin' => 'Bar',],
        ];

        $expect = [
            ['connection' => 'Foo', 'plugin' => 'Bar',],
            ['plugin' => 'Bar', 'connection' => 'test',],
        ];

        $this->ConfigReader->readConfig($config);

        $this->assertSame($expect, $this->ConfigReader->getConfig());
    }

    public function testSetConfigFromEmptyInjection(): void
    {
        $expect = [
            ['connection' => 'test'],
        ];

        $this->ConfigReader->readConfig();

        $this->assertSame($expect, $this->ConfigReader->getConfig());
    }

    public function testSetConfigWithConfigureAndInjection(): void
    {
        $config1 = [
            'connection' => 'Foo1_testSetConfigWithConfigureAndInjection',
            'plugin' => 'Bar1_testSetConfigWithConfigureAndInjection',
        ];

        $this->ConfigReader->readConfig($config1);
        $this->assertSame([$config1], $this->ConfigReader->getConfig());
    }

    public function testReadMigrationsInDatasource(): void
    {
        $this->setDummyConnections();
        $this->ConfigReader->readMigrationsInDatasources();
        // Read empty config will not overwrite Datasource config
        $this->ConfigReader->readConfig();
        $act = $this->ConfigReader->getConfig();
        $expected = [
            ['source' => 'FooSource', 'connection' => 'test_migrator'],
            ['plugin' => 'FooPlugin', 'connection' => 'test_migrator'],
            ['plugin' => 'BarPlugin', 'connection' => 'test_migrator_2'],
            ['connection' => 'test_migrator_3'],
        ];
        $this->assertSame($expected, $act);
    }

    public function testReadMigrationsInDatasourceAndInjection(): void
    {
        $this->ConfigReader->readMigrationsInDatasources();
        // Read non-empty config will overwrite Datasource config
        $this->ConfigReader->readConfig(['source' => 'Foo']);
        $act = $this->ConfigReader->getConfig();
        $expected = [
            ['source' => 'Foo', 'connection' => 'test'],
        ];
        $this->assertSame($expected, $act);
    }

    public function arrays(): array
    {
        return [
            [['a' => 'b'], [['a' => 'b']]],
            [[['a' => 'b']], [['a' => 'b']]],
            [[], []],
        ];
    }

    /**
     * @dataProvider arrays
     * @param        array $input
     * @param        array $expect
     */
    public function testNormalizeArray(array $input, array $expect): void
    {
        $this->ConfigReader->normalizeArray($input);
        $this->assertSame($expect, $input);
    }
}
