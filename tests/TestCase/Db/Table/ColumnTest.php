<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Table;

use Cake\Core\Configure;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\ValueBinder;
use Migrations\Db\Literal;
use Migrations\Db\Table\Column;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ColumnTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        // Restore bootstrap defaults
        Configure::write('Migrations.unsigned_primary_keys', true);
        Configure::write('Migrations.column_null_default', true);
        Configure::delete('Migrations.unsigned_ints');
    }

    public function testNullConstructorParameter(): void
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

    public function testSetOptionThrowsExceptionIfOptionIsNotString(): void
    {
        $column = new Column();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"0" is not a valid column option.');

        $column->setOptions(['identity']);
    }

    public function testSetOptionsIdentity(): void
    {
        $column = new Column();
        $this->assertTrue($column->isNull());
        $this->assertFalse($column->isIdentity());

        $column->setOptions(['identity' => true]);
        $this->assertFalse($column->isNull());
        $this->assertTrue($column->isIdentity());
    }

    public function testColumnNullFeatureFlag(): void
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

    public function testIntegerColumnDefaultsToSigned(): void
    {
        $column = new Column();
        $column->setName('user_id')->setType('integer');

        $this->assertFalse($column->isUnsigned());
        $this->assertTrue($column->isSigned());
        $this->assertFalse($column->getUnsigned());
    }

    public function testBigIntegerColumnDefaultsToSigned(): void
    {
        $column = new Column();
        $column->setName('big_id')->setType('biginteger');

        $this->assertFalse($column->isUnsigned());
        $this->assertTrue($column->isSigned());
        $this->assertFalse($column->getUnsigned());
    }

    public function testSmallIntegerColumnDefaultsToSigned(): void
    {
        $column = new Column();
        $column->setName('small_id')->setType('smallinteger');

        $this->assertFalse($column->isUnsigned());
        $this->assertTrue($column->isSigned());
        $this->assertFalse($column->getUnsigned());
    }

    public function testTinyIntegerColumnDefaultsToSigned(): void
    {
        $column = new Column();
        $column->setName('tiny_id')->setType('tinyinteger');

        $this->assertFalse($column->isUnsigned());
        $this->assertTrue($column->isSigned());
        $this->assertFalse($column->getUnsigned());
    }

    public function testNonIntegerColumnDoesNotDefaultToUnsigned(): void
    {
        $stringColumn = new Column();
        $stringColumn->setName('name')->setType('string');
        $this->assertFalse($stringColumn->getUnsigned());
        $this->assertFalse($stringColumn->isUnsigned());

        $dateColumn = new Column();
        $dateColumn->setName('created')->setType('datetime');
        $this->assertFalse($dateColumn->getUnsigned());
        $this->assertFalse($dateColumn->isUnsigned());

        $decimalColumn = new Column();
        $decimalColumn->setName('price')->setType('decimal');
        $this->assertFalse($decimalColumn->getUnsigned());
        $this->assertFalse($decimalColumn->isUnsigned());
    }

    public function testExplicitSignedOverridesDefault(): void
    {
        $column = new Column();
        $column->setName('counter')->setType('integer')->setSigned(true);

        $this->assertFalse($column->isUnsigned());
        $this->assertTrue($column->isSigned());
        $this->assertFalse($column->getUnsigned());
    }

    public function testExplicitUnsignedIsPreserved(): void
    {
        $column = new Column();
        $column->setName('age')->setType('integer')->setUnsigned(true);

        $this->assertTrue($column->isUnsigned());
        $this->assertFalse($column->isSigned());
        $this->assertTrue($column->getUnsigned());
    }

    public function testToArrayReturnsFalseForIntegersByDefault(): void
    {
        $column = new Column();
        $column->setName('user_id')->setType('integer');

        $result = $column->toArray();
        // getUnsigned() returns false for integer types by default (signed)
        $this->assertFalse($result['unsigned']);
    }

    public function testToArrayReturnsFalseForNonIntegerTypes(): void
    {
        $column = new Column();
        $column->setName('title')->setType('string');

        $result = $column->toArray();
        $this->assertFalse($result['unsigned']);
    }

    public function testToArrayRespectsExplicitSigned(): void
    {
        $column = new Column();
        $column->setName('offset')->setType('integer')->setSigned(true);

        $result = $column->toArray();
        $this->assertFalse($result['unsigned']);
    }

    public function testUnsignedIntsConfiguration(): void
    {
        // Without configuration, integers default to signed
        Configure::delete('Migrations.unsigned_ints');
        $column = new Column();
        $column->setName('count')->setType('integer');
        $this->assertFalse($column->isUnsigned());
        $this->assertTrue($column->isSigned());

        // With configuration enabled, integers default to unsigned
        Configure::write('Migrations.unsigned_ints', true);
        $column = new Column();
        $column->setName('count')->setType('integer');
        $this->assertTrue($column->isUnsigned());
        $this->assertFalse($column->isSigned());

        // Explicit signed overrides configuration
        $column = new Column();
        $column->setName('offset')->setType('integer')->setSigned(true);
        $this->assertFalse($column->isUnsigned());
        $this->assertTrue($column->isSigned());
    }

    public function testUnsignedPrimaryKeysConfiguration(): void
    {
        // Without configuration, identity columns default to signed
        Configure::delete('Migrations.unsigned_primary_keys');
        $column = new Column();
        $column->setName('id')->setType('integer')->setIdentity(true);
        $this->assertFalse($column->isUnsigned());
        $this->assertTrue($column->isSigned());

        // With configuration enabled, identity columns default to unsigned
        Configure::write('Migrations.unsigned_primary_keys', true);
        $column = new Column();
        $column->setName('id')->setType('integer')->setIdentity(true);
        $this->assertTrue($column->isUnsigned());
        $this->assertFalse($column->isSigned());

        // Non-identity columns are not affected by unsigned_primary_keys
        $column = new Column();
        $column->setName('user_id')->setType('integer');
        $this->assertFalse($column->isUnsigned());

        // Explicit signed overrides configuration
        $column = new Column();
        $column->setName('id')->setType('integer')->setIdentity(true)->setSigned(true);
        $this->assertFalse($column->isUnsigned());
        $this->assertTrue($column->isSigned());
    }

    public function testBothUnsignedConfigurationsWork(): void
    {
        Configure::write('Migrations.unsigned_primary_keys', true);
        Configure::write('Migrations.unsigned_ints', true);

        // Identity columns use unsigned_primary_keys configuration
        $identityColumn = new Column();
        $identityColumn->setName('id')->setType('integer')->setIdentity(true);
        $this->assertTrue($identityColumn->isUnsigned());

        // Regular integer columns use unsigned_ints configuration
        $intColumn = new Column();
        $intColumn->setName('count')->setType('integer');
        $this->assertTrue($intColumn->isUnsigned());

        // Non-integer columns are not affected
        $stringColumn = new Column();
        $stringColumn->setName('name')->setType('string');
        $this->assertFalse($stringColumn->isUnsigned());
    }

    public function testUnsignedConfigurationDoesNotAffectNonIntegerTypes(): void
    {
        Configure::write('Migrations.unsigned_ints', true);
        Configure::write('Migrations.unsigned_primary_keys', true);

        $stringColumn = new Column();
        $stringColumn->setName('name')->setType('string');
        $this->assertFalse($stringColumn->isUnsigned());

        $dateColumn = new Column();
        $dateColumn->setName('created')->setType('datetime');
        $this->assertFalse($dateColumn->isUnsigned());

        $decimalColumn = new Column();
        $decimalColumn->setName('price')->setType('decimal');
        $this->assertFalse($decimalColumn->isUnsigned());
    }

    public function testFixedOptionDefaultsToNull(): void
    {
        $column = new Column();
        $column->setName('data')->setType('binary');

        $this->assertNull($column->getFixed());
    }

    public function testSetFixedTrue(): void
    {
        $column = new Column();
        $column->setName('hash')->setType('binary')->setFixed(true);

        $this->assertTrue($column->getFixed());
    }

    public function testSetFixedFalse(): void
    {
        $column = new Column();
        $column->setName('data')->setType('binary')->setFixed(false);

        $this->assertFalse($column->getFixed());
    }

    public function testSetOptionsWithFixed(): void
    {
        $column = new Column();
        $column->setName('hash')->setType('binary');
        $column->setOptions(['fixed' => true, 'limit' => 20]);

        $this->assertTrue($column->getFixed());
        $this->assertSame(20, $column->getLimit());

        // Null via setter should be accepted and preserved
        $column2 = new Column();
        $column2->setName('data')->setType('binary')->setFixed(null);
        $this->assertNull($column2->getFixed());

        // Null via setOptions (as generated by migration snapshots)
        $column3 = new Column();
        $column3->setName('data')->setType('binary');
        $column3->setOptions(['fixed' => null, 'limit' => null, 'null' => false]);
        $this->assertNull($column3->getFixed());
    }

    public function testToArrayIncludesFixed(): void
    {
        $column = new Column();
        $column->setName('hash')->setType('binary')->setFixed(true)->setLimit(20);

        $result = $column->toArray();
        $this->assertTrue($result['fixed']);
    }

    public function testToArrayFixedNullByDefault(): void
    {
        $column = new Column();
        $column->setName('data')->setType('binary')->setLimit(20);

        $result = $column->toArray();
        $this->assertNull($result['fixed']);
    }
}
