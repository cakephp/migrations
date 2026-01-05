<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Table;

use Migrations\Db\Table\PartitionDefinition;
use PHPUnit\Framework\TestCase;

class PartitionDefinitionTest extends TestCase
{
    public function testGetName(): void
    {
        $definition = new PartitionDefinition('p2022');
        $this->assertSame('p2022', $definition->getName());
    }

    public function testGetValueNull(): void
    {
        $definition = new PartitionDefinition('p0');
        $this->assertNull($definition->getValue());
    }

    public function testGetValueString(): void
    {
        $definition = new PartitionDefinition('p2022', '2023-01-01');
        $this->assertSame('2023-01-01', $definition->getValue());
    }

    public function testGetValueInteger(): void
    {
        $definition = new PartitionDefinition('p0', 1000000);
        $this->assertSame(1000000, $definition->getValue());
    }

    public function testGetValueArray(): void
    {
        $values = ['US', 'CA', 'MX'];
        $definition = new PartitionDefinition('p_americas', $values);
        $this->assertSame($values, $definition->getValue());
    }

    public function testGetValueMaxvalue(): void
    {
        $definition = new PartitionDefinition('pmax', 'MAXVALUE');
        $this->assertSame('MAXVALUE', $definition->getValue());
    }

    public function testGetTablespace(): void
    {
        $definition = new PartitionDefinition('p2022', '2023-01-01', 'fast_storage');
        $this->assertSame('fast_storage', $definition->getTablespace());

        $definition = new PartitionDefinition('p2022', '2023-01-01');
        $this->assertNull($definition->getTablespace());
    }

    public function testGetTable(): void
    {
        $definition = new PartitionDefinition('p2022', '2023-01-01', null, 'orders_archive_2022');
        $this->assertSame('orders_archive_2022', $definition->getTable());

        $definition = new PartitionDefinition('p2022', '2023-01-01');
        $this->assertNull($definition->getTable());
    }

    public function testGetComment(): void
    {
        $definition = new PartitionDefinition('p2022', '2023-01-01', null, null, 'Archive partition for 2022');
        $this->assertSame('Archive partition for 2022', $definition->getComment());

        $definition = new PartitionDefinition('p2022', '2023-01-01');
        $this->assertNull($definition->getComment());
    }

    public function testFullConstructor(): void
    {
        $definition = new PartitionDefinition(
            'p2022',
            '2023-01-01',
            'fast_storage',
            'orders_2022',
            'Archive for 2022',
        );

        $this->assertSame('p2022', $definition->getName());
        $this->assertSame('2023-01-01', $definition->getValue());
        $this->assertSame('fast_storage', $definition->getTablespace());
        $this->assertSame('orders_2022', $definition->getTable());
        $this->assertSame('Archive for 2022', $definition->getComment());
    }

    public function testCompositeKeyValue(): void
    {
        $definition = new PartitionDefinition('p2023_east', [2023, 'east']);
        $this->assertSame([2023, 'east'], $definition->getValue());
    }

    public function testRangeFromTo(): void
    {
        $definition = new PartitionDefinition('p2022', ['from' => '2022-01-01', 'to' => '2023-01-01']);
        $this->assertSame(['from' => '2022-01-01', 'to' => '2023-01-01'], $definition->getValue());
    }
}
