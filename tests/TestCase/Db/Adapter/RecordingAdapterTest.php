<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use Migrations\Db\Adapter\RecordingAdapter;
use Migrations\Db\Table;
use Migrations\Db\Table\Column;
use Migrations\Migration\IrreversibleMigrationException;
use PHPUnit\Framework\TestCase;

class RecordingAdapterTest extends TestCase
{
    /**
     * @var \Migrations\Db\Adapter\RecordingAdapter
     */
    private $adapter;

    protected function setUp(): void
    {
        $stub = $this->getMockBuilder('\Migrations\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->getMock();

        $stub->expects($this->any())
            ->method('isValidColumnType')
            ->will($this->returnValue(true));

        $this->adapter = new RecordingAdapter($stub);
    }

    protected function tearDown(): void
    {
        unset($this->adapter);
    }

    public function testRecordingAdapterCanInvertCreateTable()
    {
        $table = new Table('atable', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Migrations\Db\Action\DropTable', $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
    }

    public function testRecordingAdapterCanInvertRenameTable()
    {
        $table = new Table('oldname', [], $this->adapter);
        $table->rename('newname')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Migrations\Db\Action\RenameTable', $commands[0]);
        $this->assertEquals('newname', $commands[0]->getTable()->getName());
        $this->assertEquals('oldname', $commands[0]->getNewName());
    }

    public function testRecordingAdapterCanInvertAddColumn()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

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
        $this->assertInstanceOf('Migrations\Db\Action\RemoveColumn', $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
        $this->assertEquals('acolumn', $commands[0]->getColumn()->getName());
    }

    public function testRecordingAdapterCanInvertRenameColumn()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new Table('atable', [], $this->adapter);
        $table->renameColumn('oldname', 'newname')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Migrations\Db\Action\RenameColumn', $commands[0]);
        $this->assertEquals('newname', $commands[0]->getColumn()->getName());
        $this->assertEquals('oldname', $commands[0]->getNewName());
    }

    public function testRecordingAdapterCanInvertAddIndex()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new Table('atable', [], $this->adapter);
        $table->addIndex(['email'])
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Migrations\Db\Action\DropIndex', $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
        $this->assertEquals(['email'], $commands[0]->getIndex()->getColumns());
    }

    public function testRecordingAdapterCanInvertAddForeignKey()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new Table('atable', [], $this->adapter);
        $table->addForeignKey(['ref_table_id'], 'refTable')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Migrations\Db\Action\DropForeignKey', $commands[0]);
        $this->assertEquals('atable', $commands[0]->getTable()->getName());
        $this->assertEquals(['ref_table_id'], $commands[0]->getForeignKey()->getColumns());
    }

    public function testGetInvertedCommandsThrowsExceptionForIrreversibleCommand()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new Table('atable', [], $this->adapter);
        $table->removeColumn('thing')
              ->save();

        $this->expectException(IrreversibleMigrationException::class);
        $this->expectExceptionMessage('Cannot reverse a "Migrations\Db\Action\RemoveColumn" command');

        $this->adapter->getInvertedCommands();
    }
}
