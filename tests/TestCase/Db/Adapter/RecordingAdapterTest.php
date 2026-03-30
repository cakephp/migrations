<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use Migrations\Db\Action\DropForeignKey;
use Migrations\Db\Action\DropIndex;
use Migrations\Db\Action\DropTable;
use Migrations\Db\Action\RemoveColumn;
use Migrations\Db\Action\RenameColumn;
use Migrations\Db\Action\RenameTable;
use Migrations\Db\Adapter\AbstractAdapter;
use Migrations\Db\Adapter\RecordingAdapter;
use Migrations\Db\Table;
use Migrations\Db\Table\Column;
use Migrations\Migration\IrreversibleMigrationException;
use PHPUnit\Framework\TestCase;

class RecordingAdapterTest extends TestCase
{
    private RecordingAdapter $adapter;

    protected function setUp(): void
    {
        $stub = $this->getMockBuilder(AbstractAdapter::class)
            ->setConstructorArgs([[]])
            ->getMock();

        $stub->expects($this->any())
            ->method('isValidColumnType')
            ->willReturn(true);

        $this->adapter = new RecordingAdapter($stub);
    }

    protected function tearDown(): void
    {
        unset($this->adapter);
    }

    public function testRecordingAdapterCanInvertCreateTable(): void
    {
        $table = new Table('atable', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf(DropTable::class, $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
    }

    public function testRecordingAdapterCanInvertRenameTable(): void
    {
        $table = new Table('oldname', [], $this->adapter);
        $table->rename('newname')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf(RenameTable::class, $commands[0]);
        $this->assertEquals('newname', $commands[0]->getTable()->getName());
        $this->assertEquals('oldname', $commands[0]->getNewName());
    }

    public function testRecordingAdapterCanInvertAddColumn(): void
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->willReturn(true);

        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('getColumnForType')
            ->willReturnCallback(function (string $columnName, string $type, array $options) {
                return (new Column())
                    ->setName($columnName)
                    ->setType($type)
                    ->setOptions($options);
            });

        $table = new Table('atable', [], $this->adapter);
        $table->addColumn('acolumn', 'string')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf(RemoveColumn::class, $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
        $this->assertEquals('acolumn', $commands[0]->getColumn()->getName());
    }

    public function testRecordingAdapterCanInvertRenameColumn(): void
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->willReturn(true);

        $table = new Table('atable', [], $this->adapter);
        $table->renameColumn('oldname', 'newname')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf(RenameColumn::class, $commands[0]);
        $this->assertEquals('newname', $commands[0]->getColumn()->getName());
        $this->assertEquals('oldname', $commands[0]->getNewName());
    }

    public function testRecordingAdapterCanInvertAddIndex(): void
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->willReturn(true);

        $table = new Table('atable', [], $this->adapter);
        $table->addIndex(['email'])
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf(DropIndex::class, $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
        $this->assertEquals(['email'], $commands[0]->getIndex()->getColumns());
    }

    public function testRecordingAdapterCanInvertAddForeignKey(): void
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->willReturn(true);

        $table = new Table('atable', [], $this->adapter);
        $table->addForeignKey(['ref_table_id'], 'refTable')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf(DropForeignKey::class, $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
        $this->assertEquals(['ref_table_id'], $commands[0]->getForeignKey()->getColumns());
    }

    public function testGetInvertedCommandsThrowsExceptionForIrreversibleCommand(): void
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->willReturn(true);

        $table = new Table('atable', [], $this->adapter);
        $table->removeColumn('thing')
              ->save();

        $this->expectException(IrreversibleMigrationException::class);
        $this->expectExceptionMessage('Cannot reverse a "Migrations\Db\Action\RemoveColumn" command');

        $this->adapter->getInvertedCommands();
    }
}
