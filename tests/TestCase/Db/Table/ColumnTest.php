<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Table;

use Cake\Core\Configure;
use Migrations\Db\Table\Column;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ColumnTest extends TestCase
{
    public function testSetOptionThrowsExceptionIfOptionIsNotString()
    {
        $column = new Column();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"0" is not a valid column option.');

        $column->setOptions(['identity']);
    }

    public function testSetOptionsIdentity()
    {
        $column = new Column();
        $this->assertTrue($column->isNull());
        $this->assertFalse($column->isIdentity());

        $column->setOptions(['identity' => true]);
        $this->assertFalse($column->isNull());
        $this->assertTrue($column->isIdentity());
    }

    #[RunInSeparateProcess]
    public function testColumnNullFeatureFlag()
    {
        $column = new Column();
        $this->assertTrue($column->isNull());

        Configure::write('Migrations.column_null_default', false);
        $column = new Column();
        $this->assertFalse($column->isNull());
    }
}
