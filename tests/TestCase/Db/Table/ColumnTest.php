<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Table;

use Cake\Core\Configure;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\ValueBinder;
use Migrations\Db\Literal;
use Migrations\Db\Table\Column;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ColumnTest extends TestCase
{
    public function testNullConstructorParameter()
    {
        $column = new Column(name: 'title');
        $this->assertTrue($column->isNull());

        $column = new Column(name: 'title', null: true);
        $this->assertTrue($column->isNull());

        $column = new Column(name: 'title', null: false);
        $this->assertFalse($column->isNull());

        Configure::write('Migrations.column_null_default', true);
        $column = new Column(name: 'title');
        $this->assertTrue($column->isNull());
    }

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

    public function testToArrayDefaultLiteralValue(): void
    {
        $column = new Column();
        $column->setName('created')
            ->setType('datetime')
            ->setDefault(new Literal('CURRENT_TIMESTAMP'));
        $result = $column->toArray();
        $this->assertInstanceOf(QueryExpression::class, $result['default']);
        $this->assertEquals('CURRENT_TIMESTAMP', $result['default']->sql(new ValueBinder()));
    }
}
