<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Plan;

use Migrations\Db\Action\AddPartition;
use Migrations\Db\Action\DropPartition;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Plan\Intent;
use Migrations\Db\Plan\Plan;
use Migrations\Db\Table\PartitionDefinition;
use Migrations\Db\Table\TableMetadata;
use PHPUnit\Framework\TestCase;

class PlanTest extends TestCase
{
    public function testPartitionActionsAreExecuted()
    {
        $table = new TableMetadata('orders');
        $intent = new Intent();
        $intent->addAction(new AddPartition($table, new PartitionDefinition('p2024', '2025-01-01')));
        $intent->addAction(new DropPartition($table, 'p2023'));

        $plan = new Plan($intent);

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->never())->method('createTable');
        $adapter->expects($this->once())
            ->method('executeActions')
            ->with(
                $this->callback(fn (TableMetadata $passedTable) => $passedTable->getName() === 'orders'),
                $this->callback(function (array $actions): bool {
                    $this->assertCount(2, $actions);
                    $this->assertInstanceOf(AddPartition::class, $actions[0]);
                    $this->assertInstanceOf(DropPartition::class, $actions[1]);

                    return true;
                }),
            );

        $plan->execute($adapter);
    }
}
