<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use Migrations\Db\Adapter\PdoAdapter;
use Migrations\Test\TestCase\Db\Adapter\DefaultPdoAdapterTrait;
use PDOException;
use Phinx\Config\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PdoAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\PdoAdapter|\PHPUnit\Framework\MockObject\MockObject
     */
    private $adapter;

    protected function setUp(): void
    {
        $this->adapter = new class (['foo' => 'bar', 'version_order' => Config::VERSION_ORDER_CREATION_TIME]) extends PdoAdapter {
            use DefaultPdoAdapterTrait;
        };
    }

    protected function tearDown(): void
    {
        unset($this->adapter);
    }

    public function testOptions()
    {
        $options = $this->adapter->getOptions();
        $this->assertArrayHasKey('foo', $options);
        $this->assertEquals('bar', $options['foo']);
    }

    public function testOptionsSetSchemaTableName()
    {
        $this->assertEquals('phinxlog', $this->adapter->getSchemaTableName());
        $this->adapter->setOptions(['migration_table' => 'schema_table_test']);
        $this->assertEquals('schema_table_test', $this->adapter->getSchemaTableName());
    }

    public function testSchemaTableName()
    {
        $this->assertEquals('phinxlog', $this->adapter->getSchemaTableName());
        $this->adapter->setSchemaTableName('schema_table_test');
        $this->assertEquals('schema_table_test', $this->adapter->getSchemaTableName());
    }

    #[DataProvider('getVersionLogDataProvider')]
    public function testGetVersionLog($versionOrder, $expectedOrderBy)
    {
        $adapter = new class (['version_order' => $versionOrder]) extends PdoAdapter {
            use DefaultPdoAdapterTrait;

            public function getSchemaTableName(): string
            {
                return 'log';
            }

            public function quoteTableName(string $tableName): string
            {
                return "'$tableName'";
            }

            public function fetchAll(string $sql): array
            {
                return [
                    [
                        'version' => '20120508120534',
                        'key' => 'value',
                    ],
                    [
                        'version' => '20130508120534',
                        'key' => 'value',
                    ],
                ];
            }
        };

        // we expect the mock rows but indexed by version creation time
        $expected = [
            '20120508120534' => [
                'version' => '20120508120534',
                'key' => 'value',
            ],
            '20130508120534' => [
                'version' => '20130508120534',
                'key' => 'value',
            ],
        ];

        $this->assertEquals($expected, $adapter->getVersionLog());
    }

    public static function getVersionLogDataProvider()
    {
        return [
            'With Creation Time Version Order' => [
                Config::VERSION_ORDER_CREATION_TIME, 'version ASC',
            ],
            'With Execution Time Version Order' => [
                Config::VERSION_ORDER_EXECUTION_TIME, 'start_time ASC, version ASC',
            ],
        ];
    }

    public function testGetVersionLogInvalidVersionOrderKO()
    {
        $this->expectExceptionMessage('Invalid version_order configuration option');
        $adapter = new class (['version_order' => 'invalid']) extends PdoAdapter {
            use DefaultPdoAdapterTrait;
        };

        $this->expectException(RuntimeException::class);

        $adapter->getVersionLog();
    }

    public function testGetVersionLongDryRun()
    {
        $adapter = new class (['version_order' => Config::VERSION_ORDER_CREATION_TIME]) extends PdoAdapter {
            use DefaultPdoAdapterTrait;

            public function isDryRunEnabled(): bool
            {
                return true;
            }

            public function getSchemaTableName(): string
            {
                return 'log';
            }

            public function fetchAll(string $sql): array
            {
                throw new PDOException();
            }
        };

        $this->assertEquals([], $adapter->getVersionLog());
    }
}
