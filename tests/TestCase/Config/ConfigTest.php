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
    public function testGetEnvironmentMethod(): void
    {
        $config = new Config($this->getConfigArray());
        $db = $config->getEnvironment();
        $this->assertArrayHasKey('adapter', $db);
    }

    public function testEnvironmentHasMigrationTable(): void
    {
        $configArray = $this->getConfigArray();
        $configArray['environment']['migration_table'] = 'test_table';
        $config = new Config($configArray);

        $this->assertSame('test_table', $config->getEnvironment()['migration_table']);
    }

    public function testArrayAccessMethods(): void
    {
        $config = new Config([]);
        $config['foo'] = 'bar';
        $this->assertEquals('bar', $config['foo']);
        $this->assertArrayHasKey('foo', $config);
        unset($config['foo']);
        $this->assertArrayNotHasKey('foo', $config);
    }

    public function testUndefinedArrayAccess(): void
    {
        $config = new Config([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier "foo" is not defined.');

        $config['foo'];
    }

    public function testGetSeedPath(): void
    {
        $config = new Config(['paths' => ['seeds' => 'db/seeds']]);
        $this->assertEquals('db/seeds', $config->getSeedPath());

        $config = new Config(['paths' => ['seeds' => ['db/seeds1', 'db/seeds2']]]);
        $this->assertEquals('db/seeds1', $config->getSeedPath());
    }

    public function testGetSeedPathThrowsException(): void
    {
        $config = new Config([]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Seeds path missing from config file');

        $config->getSeedPath();
    }

    public function testGetVersionOrder(): void
    {
        $config = new Config([]);
        $config['version_order'] = Config::VERSION_ORDER_EXECUTION_TIME;
        $this->assertEquals(Config::VERSION_ORDER_EXECUTION_TIME, $config->getVersionOrder());
    }

    #[DataProvider('isVersionOrderCreationTimeDataProvider')]
    public function testIsVersionOrderCreationTime(string $versionOrder, bool $expected): void
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

    public static function isVersionOrderCreationTimeDataProvider(): array
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

    public function testIsDryRunDefaultFalse(): void
    {
        $config = new Config([]);
        $this->assertFalse($config->isDryRun());
    }

    public function testIsDryRunWhenTrue(): void
    {
        $config = new Config([
            'environment' => [
                'dryrun' => true,
            ],
        ]);
        $this->assertTrue($config->isDryRun());
    }

    public function testIsDryRunWhenFalse(): void
    {
        $config = new Config([
            'environment' => [
                'dryrun' => false,
            ],
        ]);
        $this->assertFalse($config->isDryRun());
    }

    public function testIsDryRunWhenNotSet(): void
    {
        $config = new Config([
            'environment' => [
                'adapter' => 'mysql',
            ],
        ]);
        $this->assertFalse($config->isDryRun());
    }
}
