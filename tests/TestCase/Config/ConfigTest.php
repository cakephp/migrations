<?php

namespace Migrations\Test\TestCase\Config;

use InvalidArgumentException;
use Migrations\Config\Config;
use UnexpectedValueException;

/**
 * Class ConfigTest
 *
 * @package Test\Phinx\Config
 * @group config
 */
class ConfigTest extends AbstractConfigTestCase
{
    /**
     * @covers \Phinx\Config\Config::getEnvironment
     */
    public function testGetEnvironmentMethod()
    {
        $config = new Config($this->getConfigArray());
        $db = $config->getEnvironment();
        $this->assertArrayHasKey('adapter', $db);
    }

    public function testEnvironmentHasMigrationTable()
    {
        $configArray = $this->getConfigArray();
        $configArray['environment']['migration_table'] = 'test_table';
        $config = new Config($configArray);

        $this->assertSame('test_table', $config->getEnvironment()['migration_table']);
    }

    /**
     * @covers \Phinx\Config\Config::offsetGet
     * @covers \Phinx\Config\Config::offsetSet
     * @covers \Phinx\Config\Config::offsetExists
     * @covers \Phinx\Config\Config::offsetUnset
     */
    public function testArrayAccessMethods()
    {
        $config = new Config([]);
        $config['foo'] = 'bar';
        $this->assertEquals('bar', $config['foo']);
        $this->assertArrayHasKey('foo', $config);
        unset($config['foo']);
        $this->assertArrayNotHasKey('foo', $config);
    }

    /**
     * @covers \Phinx\Config\Config::offsetGet
     */
    public function testUndefinedArrayAccess()
    {
        $config = new Config([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier "foo" is not defined.');

        $config['foo'];
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsDefaultBaseClass()
    {
        $config = new Config([]);
        $this->assertEquals('AbstractMigration', $config->getMigrationBaseClassName());
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsDefaultBaseClassWithNamespace()
    {
        $config = new Config([]);
        $this->assertEquals('Phinx\Migration\AbstractMigration', $config->getMigrationBaseClassName(false));
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsAlternativeBaseClass()
    {
        $config = new Config(['migration_base_class' => 'Phinx\Migration\AlternativeAbstractMigration']);
        $this->assertEquals('AlternativeAbstractMigration', $config->getMigrationBaseClassName());
    }

    /**
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameGetsAlternativeBaseClassWithNamespace()
    {
        $config = new Config(['migration_base_class' => 'Phinx\Migration\AlternativeAbstractMigration']);
        $this->assertEquals('Phinx\Migration\AlternativeAbstractMigration', $config->getMigrationBaseClassName(false));
    }

    /**
     * @covers \Phinx\Config\Config::getTemplateFile
     * @covers \Phinx\Config\Config::getTemplateClass
     */
    public function testGetTemplateValuesFalseOnEmpty()
    {
        $config = new Config([]);
        $this->assertFalse($config->getTemplateFile());
        $this->assertFalse($config->getTemplateClass());
    }

    public function testGetSeedPath()
    {
        $config = new Config(['paths' => ['seeds' => 'db/seeds']]);
        $this->assertEquals('db/seeds', $config->getSeedPath());

        $config = new Config(['paths' => ['seeds' => ['db/seeds1', 'db/seeds2']]]);
        $this->assertEquals('db/seeds1', $config->getSeedPath());
    }

    /**
     * @covers \Phinx\Config\Config::getSeedPaths
     */
    public function testGetSeedPathThrowsException()
    {
        $config = new Config([]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Seeds path missing from config file');

        $config->getSeedPath();
    }

    /**
     * Checks if base class is returned correctly when specified without
     * a namespace.
     *
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameNoNamespace()
    {
        $config = new Config(['migration_base_class' => 'BaseMigration']);
        $this->assertEquals('BaseMigration', $config->getMigrationBaseClassName());
    }

    /**
     * Checks if base class is returned correctly when specified without
     * a namespace.
     *
     * @covers \Phinx\Config\Config::getMigrationBaseClassName
     */
    public function testGetMigrationBaseClassNameNoNamespaceNoDrop()
    {
        $config = new Config(['migration_base_class' => 'BaseMigration']);
        $this->assertEquals('BaseMigration', $config->getMigrationBaseClassName(false));
    }

    /**
     * @covers \Phinx\Config\Config::getVersionOrder
     */
    public function testGetVersionOrder()
    {
        $config = new Config([]);
        $config['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;
        $this->assertEquals(Config::VERSION_ORDER_EXECUTION_TIME, $config->getVersionOrder());
    }

    /**
     * @covers \Phinx\Config\Config::isVersionOrderCreationTime
     * @dataProvider isVersionOrderCreationTimeDataProvider
     */
    public function testIsVersionOrderCreationTime($versionOrder, $expected)
    {
        // get config stub
        $configStub = $this->getMockBuilder(Config::class)
            ->onlyMethods(['getVersionOrder'])
            ->setConstructorArgs([[]])
            ->getMock();

        $configStub->expects($this->once())
            ->method('getVersionOrder')
            ->willReturn($versionOrder);

        $this->assertEquals($expected, $configStub->isVersionOrderCreationTime());
    }

    /**
     * @covers \Phinx\Config\Config::isVersionOrderCreationTime
     */
    public static function isVersionOrderCreationTimeDataProvider()
    {
        return [
            'With Creation Time Version Order' =>
            [
                Config::VERSION_ORDER_CREATION_TIME, true,
            ],
            'With Execution Time Version Order' =>
            [
                Config::VERSION_ORDER_EXECUTION_TIME, false,
            ],
        ];
    }

    public function testDefaultTemplateStyle(): void
    {
        $config = new Config([]);
        $this->assertSame('change', $config->getTemplateStyle());
    }

    public static function templateStyleDataProvider(): array
    {
        return [
            ['change', 'change'],
            ['up_down', 'up_down'],
            ['foo', 'change'],
        ];
    }

    /**
     * @dataProvider templateStyleDataProvider
     */
    public function testTemplateStyle(string $style, string $expected): void
    {
        $config = new Config(['templates' => ['style' => $style]]);
        $this->assertSame($expected, $config->getTemplateStyle());
    }
}
