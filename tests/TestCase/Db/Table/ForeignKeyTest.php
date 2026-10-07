<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Table;

use InvalidArgumentException;
use Migrations\Db\Table\ForeignKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ForeignKeyTest extends TestCase
{
    private ForeignKey $fk;

    protected function setUp(): void
    {
        $this->fk = new ForeignKey();
    }

    public function testName(): void
    {
        $this->assertSame('', $this->fk->getName());
        $this->assertSame($this->fk, $this->fk->setName('fk_name'));
        $this->assertEquals('fk_name', $this->fk->getName());
    }

    public function testReferencedColumns(): void
    {
        $this->assertEquals([], $this->fk->getReferencedColumns());
        $this->assertSame($this->fk, $this->fk->setReferencedColumns('user_id'));
        $this->assertEquals(['user_id'], $this->fk->getReferencedColumns());

        $this->assertSame($this->fk, $this->fk->setReferencedColumns(['user_id', 'tenant_id']));
        $this->assertEquals(['user_id', 'tenant_id'], $this->fk->getReferencedColumns());
    }

    public function testOnDeleteSetNullCanBeSetThroughOptions(): void
    {
        $this->assertEquals(
            ForeignKey::SET_NULL,
            $this->fk->setOptions(['delete' => ForeignKey::SET_NULL])->getOnDelete(),
        );
    }

    public function testInitiallyActionsEmpty(): void
    {
        $this->assertSame(ForeignKey::NO_ACTION, $this->fk->getOnDelete());
        $this->assertSame(ForeignKey::NO_ACTION, $this->fk->getOnUpdate());
    }

    #[DataProvider('actionsProvider')]
    public function testBothActionsCanBeSetThroughSetters(string $dirtyValue, string $valueOfConstant): void
    {
        $this->fk->setOnDelete($dirtyValue)->setOnUpdate($dirtyValue);
        $this->assertEquals($valueOfConstant, $this->fk->getOnDelete());
        $this->assertEquals($valueOfConstant, $this->fk->getOnUpdate());
    }

    #[DataProvider('actionsProvider')]
    public function testBothActionsCanBeSetThroughOptions(string $dirtyValue, string $valueOfConstant): void
    {
        $this->fk->setOptions([
            'delete' => $dirtyValue,
            'update' => $dirtyValue,
        ]);
        $this->assertEquals($valueOfConstant, $this->fk->getOnDelete());
        $this->assertEquals($valueOfConstant, $this->fk->getOnUpdate());
    }

    public function testUnknownActionsNotAllowedThroughSetter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->fk->setOnDelete('i m dump');
    }

    public function testUnknownActionsNotAllowedThroughOptions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->fk->setOptions(['update' => 'no yu a dumb']);
    }

    public static function actionsProvider(): array
    {
        return [
            [ForeignKey::CASCADE, ForeignKey::CASCADE],
            [ForeignKey::RESTRICT, ForeignKey::RESTRICT],
            [ForeignKey::NO_ACTION, ForeignKey::NO_ACTION],
            [ForeignKey::SET_NULL, ForeignKey::SET_NULL],
            ['no Action ', ForeignKey::NO_ACTION],
            ['Set nuLL', ForeignKey::SET_NULL],
            ['no_Action', ForeignKey::NO_ACTION],
            ['Set_nuLL', ForeignKey::SET_NULL],
        ];
    }

    public function testSetOptionThrowsExceptionIfOptionIsNotString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"0" is not a valid foreign key option');

        $this->fk->setOptions(['update']);
    }

    #[DataProvider('deferrableProvider')]
    public function testDeferrableCanBeSetThroughSetters(string $dirtyValue, string $valueOfConstant): void
    {
        $this->fk->setDeferrableMode($dirtyValue);
        $this->assertEquals($valueOfConstant, $this->fk->getDeferrableMode());
    }

    #[DataProvider('deferrableProvider')]
    public function testDeferrableCanBeSetThroughOptions(string $dirtyValue, string $valueOfConstant): void
    {
        $this->fk->setOptions([
            'deferrable' => $dirtyValue,
        ]);
        $this->assertEquals($valueOfConstant, $this->fk->getDeferrableMode());
    }

    public static function deferrableProvider(): array
    {
        return [
            ['DEFERRED', ForeignKey::DEFERRED],
            ['IMMEDIATE', ForeignKey::IMMEDIATE],
            ['NOT_DEFERRED', ForeignKey::NOT_DEFERRED],
            ['Deferred', ForeignKey::DEFERRED],
            ['Immediate', ForeignKey::IMMEDIATE],
            ['Not_deferred', ForeignKey::NOT_DEFERRED],
            [ForeignKey::DEFERRED, ForeignKey::DEFERRED],
            [ForeignKey::IMMEDIATE, ForeignKey::IMMEDIATE],
            [ForeignKey::NOT_DEFERRED, ForeignKey::NOT_DEFERRED],
        ];
    }

    public function testThrowsErrorForInvalidDeferrableValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fk->setDeferrableMode('invalid_value');
    }
}
