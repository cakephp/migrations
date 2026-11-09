<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use Cake\Database\Schema\SchemaDialect;
use Cake\Database\Schema\TableSchema;
use Migrations\Db\Adapter\SqlserverAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SQL Server schema reflection without a database connection.
 */
class SqlserverAdapterSchemaTest extends TestCase
{
    /**
     * @return array<string, array<string>>
     */
    public static function binaryTypesProvider(): array
    {
        return [
            'fixed binary' => [TableSchema::TYPE_BINARY],
            'variable binary' => [TableSchema::TYPE_VARBINARY],
        ];
    }

    /**
     * Binary schema types retain the migration type and column metadata.
     *
     * @param string $type CakePHP's reflected column type.
     * @return void
     */
    #[DataProvider('binaryTypesProvider')]
    public function testBinaryColumnReflection(string $type): void
    {
        $dialect = $this->createMock(SchemaDialect::class);
        $dialect->expects($this->once())
            ->method('describeColumns')
            ->with('assets')
            ->willReturn([[
                'name' => 'payload',
                'type' => $type,
                'null' => true,
                'length' => 64,
                'default' => null,
                'comment' => 'Binary payload',
            ]]);

        $adapter = $this->getMockBuilder(SqlserverAdapter::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['getSchemaDialect'])
            ->getMock();
        $adapter->expects($this->once())
            ->method('getSchemaDialect')
            ->willReturn($dialect);

        $columns = $adapter->getColumns('assets');

        $this->assertCount(1, $columns);
        $this->assertSame('payload', $columns[0]->getName());
        $this->assertSame(SqlserverAdapter::TYPE_BINARY, $columns[0]->getType());
        $this->assertSame(64, $columns[0]->getLimit());
        $this->assertTrue($columns[0]->isNull());
        $this->assertNull($columns[0]->getDefault());
        $this->assertSame('Binary payload', $columns[0]->getComment());
    }
}
