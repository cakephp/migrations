<?php

namespace Migrations\Test\TestCase\Config;

use InvalidArgumentException;
use Migrations\Config\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use UnexpectedValueException;

/**
 * Class ConfigTest
 */
class ConfigTest extends AbstractConfigTestCase
{
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

    public function testArrayAccessMethods()
    {
        $config = new Config([]);
        $config['foo'] = 'bar';
        $this->assertEquals('bar', $config['foo']);
        $this->assertArrayHasKey('foo', $config);
        unset($config['foo']);
        $this->assertArrayNotHasKey('foo', $config);
    }

    public function testUndefinedArrayAccess()
    {
        $config = new Config([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier "foo" is not defined.');

        $config['foo'];
    }

    public function testGetMigrationBaseClassNameGetsDefaultBaseClass()
    {
        $config = new Config([]);
        $this->assertEquals('AbstractMigration', $config->getMigrationBaseClassName());
    }

    public function testGetMigrationBaseClassNameGetsDefaultBaseClassWithNamespace()
    {
        $config = new Config([]);
        $this->assertEquals('Phinx\Migration\AbstractMigration', $config->getMigrationBaseClassName(false));
    }

    public function testGetMigrationBaseClassNameGetsAlternativeBaseClass()
    {
        $config = new Config(['migration_base_class' => 'Phinx\Migration\AlternativeAbstractMigration']);
        $this->assertEquals('AlternativeAbstractMigration', $config->getMigrationBaseClassName());
    }

    public function testGetMigrationBaseClassNameGetsAlternativeBaseClassWithNamespace()
    {
        $config = new Config(['migration_base_class' => 'Phinx\Migration\AlternativeAbstractMigration']);
        $this->assertEquals('Phinx\Migration\AlternativeAbstractMigration', $config->getMigrationBaseClassName(false));
    }

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
     */
    public function testGetMigrationBaseClassNameNoNamespace()
    {
        $config = new Config(['migration_base_class' => 'BaseMigration']);
        $this->assertEquals('BaseMigration', $config->getMigrationBaseClassName());
    }

    /**
     * Checks if base class is returned correctly when specified without
     * a namespace.
     */
    public function testGetMigrationBaseClassNameNoNamespaceNoDrop()
    {
        $config = new Config(['migration_base_class' => 'BaseMigration']);
        $this->assertEquals('BaseMigration', $config->getMigrationBaseClassName(false));
    }

    public function testGetVersionOrder()
    {
        $config = new Config([]);
        $config['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;
        $this->assertEquals(Config::VERSION_ORDER_EXECUTION_TIME, $config->getVersionOrder());
    }

    #[DataProvider('isVersionOrderCreationTimeDataProvider')]
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

    #[DataProvider('templateStyleDataProvider')]
    public function testTemplateStyle(string $style, string $expected): void
    {
        $config = new Config(['templates' => ['style' => $style]]);
        $this->assertSame($expected, $config->getTemplateStyle());
    }
}
