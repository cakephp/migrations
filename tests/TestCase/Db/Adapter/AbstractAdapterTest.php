<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Migrations\Config\Config;
use Migrations\Db\Adapter\AbstractAdapter;
use Migrations\Db\Adapter\UnifiedMigrationsTableStorage;
use Migrations\Db\Literal;
use Migrations\Test\TestCase\Db\Adapter\DefaultAdapterTrait;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AbstractAdapterTest extends TestCase
{
    private AbstractAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new class (['foo' => 'bar', 'version_order' => Config::VERSION_ORDER_CREATION_TIME]) extends AbstractAdapter {
            use DefaultAdapterTrait;
        };
    }

    protected function tearDown(): void
    {
        unset($this->adapter);
    }

    public function testOptions(): void
    {
        $options = $this->adapter->getOptions();
        $this->assertArrayHasKey('foo', $options);
        $this->assertEquals('bar', $options['foo']);
    }

    public function testOptionsSetSchemaTableName(): void
    {
        // When unified table mode is enabled, getSchemaTableName() returns cake_migrations
        $expectedDefault = Configure::read('Migrations.legacyTables') === false
            ? UnifiedMigrationsTableStorage::TABLE_NAME
            : 'phinxlog';
        $this->assertEquals($expectedDefault, $this->adapter->getSchemaTableName());
        $this->adapter->setOptions(['migration_table' => 'schema_table_test']);
        // After explicitly setting migration_table, it should use that value in legacy mode
        // But unified mode always returns cake_migrations
        $expectedAfterSet = Configure::read('Migrations.legacyTables') === false
            ? UnifiedMigrationsTableStorage::TABLE_NAME
            : 'schema_table_test';
        $this->assertEquals($expectedAfterSet, $this->adapter->getSchemaTableName());
    }

    public function testSchemaTableName(): void
    {
        $expectedDefault = Configure::read('Migrations.legacyTables') === false
            ? UnifiedMigrationsTableStorage::TABLE_NAME
            : 'phinxlog';
        $this->assertEquals($expectedDefault, $this->adapter->getSchemaTableName());
        $this->adapter->setSchemaTableName('schema_table_test');
        $expectedAfterSet = Configure::read('Migrations.legacyTables') === false
            ? UnifiedMigrationsTableStorage::TABLE_NAME
            : 'schema_table_test';
        $this->assertEquals($expectedAfterSet, $this->adapter->getSchemaTableName());
    }

    public function testGetVersionLogInvalidVersionOrderKO(): void
    {
        $this->expectExceptionMessage('Invalid version_order configuration option');
        $adapter = new class (['version_order' => 'invalid']) extends AbstractAdapter {
            use DefaultAdapterTrait;
        };

        $this->expectException(RuntimeException::class);

        $adapter->getVersionLog();
    }

    public function testGetVersionLongDryRun(): void
    {
        $adapter = new class (['version_order' => Config::VERSION_ORDER_CREATION_TIME]) extends AbstractAdapter {
            use DefaultAdapterTrait;

            public function getConnection(): Connection
            {
                return ConnectionManager::get('test');
            }

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

    /**
     * Test CURRENT_TIMESTAMP default value quoting behavior (bug #1891).
     *
     * This test verifies that CURRENT_TIMESTAMP is only treated as a SQL function
     * for datetime-related column types. For other column types like string,
     * 'CURRENT_TIMESTAMP' should be quoted as a literal string value.
     *
     * @see https://github.com/cakephp/phinx/issues/1891
     */
    #[DataProvider('currentTimestampDefaultValueProvider')]
    public function testCurrentTimestampDefaultValueQuoting(string|Literal|bool $default, ?string $columnType, string $expected): void
    {
        $adapter = new class (['version_order' => Config::VERSION_ORDER_CREATION_TIME]) extends AbstractAdapter {
            use DefaultAdapterTrait;

            public function quoteString(string $value): string
            {
                return "'" . $value . "'";
            }

            // Expose the protected method for testing
            public function testGetDefaultValueDefinition(mixed $default, ?string $columnType = null): string
            {
                return $this->getDefaultValueDefinition($default, $columnType);
            }
        };

        $actual = $adapter->testGetDefaultValueDefinition($default, $columnType);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for CURRENT_TIMESTAMP quoting test.
     *
     * @return array
     */
    public static function currentTimestampDefaultValueProvider(): array
    {
        return [
            // CURRENT_TIMESTAMP on datetime types should NOT be quoted
            'CURRENT_TIMESTAMP on datetime' => [
                'CURRENT_TIMESTAMP',
                AbstractAdapter::TYPE_DATETIME,
                ' DEFAULT CURRENT_TIMESTAMP',
            ],
            'CURRENT_TIMESTAMP on timestamp' => [
                'CURRENT_TIMESTAMP',
                AbstractAdapter::TYPE_TIMESTAMP,
                ' DEFAULT CURRENT_TIMESTAMP',
            ],
            'CURRENT_TIMESTAMP on time' => [
                'CURRENT_TIMESTAMP',
                AbstractAdapter::TYPE_TIME,
                ' DEFAULT CURRENT_TIMESTAMP',
            ],
            'CURRENT_TIMESTAMP on date' => [
                'CURRENT_TIMESTAMP',
                AbstractAdapter::TYPE_DATE,
                ' DEFAULT CURRENT_TIMESTAMP',
            ],
            'CURRENT_TIMESTAMP(3) on datetime' => [
                'CURRENT_TIMESTAMP(3)',
                AbstractAdapter::TYPE_DATETIME,
                ' DEFAULT CURRENT_TIMESTAMP(3)',
            ],

            // CURRENT_DATE on date type should NOT be quoted
            'CURRENT_DATE on date' => [
                'CURRENT_DATE',
                AbstractAdapter::TYPE_DATE,
                ' DEFAULT CURRENT_DATE',
            ],
            // CURRENT_DATE on non-date types SHOULD be quoted
            'CURRENT_DATE on datetime should be quoted' => [
                'CURRENT_DATE',
                AbstractAdapter::TYPE_DATETIME,
                " DEFAULT 'CURRENT_DATE'",
            ],
            'CURRENT_DATE on string should be quoted' => [
                'CURRENT_DATE',
                AbstractAdapter::TYPE_STRING,
                " DEFAULT 'CURRENT_DATE'",
            ],

            // CURRENT_TIME on time type should NOT be quoted
            'CURRENT_TIME on time' => [
                'CURRENT_TIME',
                AbstractAdapter::TYPE_TIME,
                ' DEFAULT CURRENT_TIME',
            ],
            // CURRENT_TIME on non-time types SHOULD be quoted
            'CURRENT_TIME on datetime should be quoted' => [
                'CURRENT_TIME',
                AbstractAdapter::TYPE_DATETIME,
                " DEFAULT 'CURRENT_TIME'",
            ],

            // CURRENT_TIMESTAMP on non-datetime types SHOULD be quoted (bug #1891)
            'CURRENT_TIMESTAMP on string should be quoted' => [
                'CURRENT_TIMESTAMP',
                AbstractAdapter::TYPE_STRING,
                " DEFAULT 'CURRENT_TIMESTAMP'",
            ],
            'CURRENT_TIMESTAMP on text should be quoted' => [
                'CURRENT_TIMESTAMP',
                AbstractAdapter::TYPE_TEXT,
                " DEFAULT 'CURRENT_TIMESTAMP'",
            ],
            'CURRENT_TIMESTAMP on char should be quoted' => [
                'CURRENT_TIMESTAMP',
                AbstractAdapter::TYPE_CHAR,
                " DEFAULT 'CURRENT_TIMESTAMP'",
            ],
            'CURRENT_TIMESTAMP on integer should be quoted' => [
                'CURRENT_TIMESTAMP',
                AbstractAdapter::TYPE_INTEGER,
                " DEFAULT 'CURRENT_TIMESTAMP'",
            ],

            // Regular string defaults should always be quoted
            'Regular string default' => [
                'default_value',
                AbstractAdapter::TYPE_STRING,
                " DEFAULT 'default_value'",
            ],
            'Regular string on datetime' => [
                'some_string',
                AbstractAdapter::TYPE_DATETIME,
                " DEFAULT 'some_string'",
            ],

            // Literal values should not be quoted
            'Literal value' => [
                Literal::from('NOW()'),
                AbstractAdapter::TYPE_DATETIME,
                ' DEFAULT NOW()',
            ],

            // Boolean defaults
            'Boolean true' => [
                true,
                AbstractAdapter::TYPE_BOOLEAN,
                ' DEFAULT 1',
            ],
            'Boolean false' => [
                false,
                AbstractAdapter::TYPE_BOOLEAN,
                ' DEFAULT 0',
            ],

            // Null columnType (should quote CURRENT_TIMESTAMP when type is unknown)
            'CURRENT_TIMESTAMP with null column type' => [
                'CURRENT_TIMESTAMP',
                null,
                " DEFAULT 'CURRENT_TIMESTAMP'",
            ],
        ];
    }
}
