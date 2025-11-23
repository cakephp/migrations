<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Table;

use Migrations\Db\Literal;
use Migrations\Db\Table\Partition;
use Migrations\Db\Table\PartitionDefinition;
use PHPUnit\Framework\TestCase;

class PartitionTest extends TestCase
{
    public function testGetType(): void
    {
        $partition = new Partition(Partition::TYPE_RANGE, 'created_at');
        $this->assertSame(Partition::TYPE_RANGE, $partition->getType());

        $partition = new Partition(Partition::TYPE_LIST, 'region');
        $this->assertSame(Partition::TYPE_LIST, $partition->getType());

        $partition = new Partition(Partition::TYPE_HASH, 'user_id');
        $this->assertSame(Partition::TYPE_HASH, $partition->getType());

        $partition = new Partition(Partition::TYPE_KEY, 'cache_key');
        $this->assertSame(Partition::TYPE_KEY, $partition->getType());
    }

    public function testGetColumnsSingleColumn(): void
    {
        $partition = new Partition(Partition::TYPE_RANGE, 'created_at');
        $this->assertSame(['created_at'], $partition->getColumns());
    }

    public function testGetColumnsMultipleColumns(): void
    {
        $partition = new Partition(Partition::TYPE_RANGE, ['year', 'month']);
        $this->assertSame(['year', 'month'], $partition->getColumns());
    }

    public function testGetColumnsWithLiteral(): void
    {
        $literal = Literal::from('YEAR(created_at)');
        $partition = new Partition(Partition::TYPE_RANGE, $literal);
        $this->assertSame($literal, $partition->getColumns());
    }

    public function testGetCount(): void
    {
        $partition = new Partition(Partition::TYPE_HASH, 'user_id', [], 8);
        $this->assertSame(8, $partition->getCount());

        $partition = new Partition(Partition::TYPE_RANGE, 'created_at');
        $this->assertNull($partition->getCount());
    }

    public function testGetOptions(): void
    {
        $options = ['custom' => 'value'];
        $partition = new Partition(Partition::TYPE_HASH, 'user_id', [], 8, $options);
        $this->assertSame($options, $partition->getOptions());
    }

    public function testGetDefinitionsEmpty(): void
    {
        $partition = new Partition(Partition::TYPE_RANGE, 'created_at');
        $this->assertSame([], $partition->getDefinitions());
    }

    public function testGetDefinitionsWithInitialDefinitions(): void
    {
        $def1 = new PartitionDefinition('p2022', '2023-01-01');
        $def2 = new PartitionDefinition('p2023', '2024-01-01');
        $partition = new Partition(Partition::TYPE_RANGE, 'created_at', [$def1, $def2]);

        $definitions = $partition->getDefinitions();
        $this->assertCount(2, $definitions);
        $this->assertSame($def1, $definitions[0]);
        $this->assertSame($def2, $definitions[1]);
    }

    public function testAddDefinition(): void
    {
        $partition = new Partition(Partition::TYPE_RANGE, 'created_at');
        $def = new PartitionDefinition('p2022', '2023-01-01');

        $result = $partition->addDefinition($def);

        $this->assertSame($partition, $result);
        $this->assertCount(1, $partition->getDefinitions());
        $this->assertSame($def, $partition->getDefinitions()[0]);
    }

    public function testAddMultipleDefinitions(): void
    {
        $partition = new Partition(Partition::TYPE_RANGE, 'created_at');

        $partition->addDefinition(new PartitionDefinition('p2022', '2023-01-01'))
            ->addDefinition(new PartitionDefinition('p2023', '2024-01-01'))
            ->addDefinition(new PartitionDefinition('pmax', 'MAXVALUE'));

        $this->assertCount(3, $partition->getDefinitions());
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('RANGE', Partition::TYPE_RANGE);
        $this->assertSame('LIST', Partition::TYPE_LIST);
        $this->assertSame('HASH', Partition::TYPE_HASH);
        $this->assertSame('KEY', Partition::TYPE_KEY);
    }
}
