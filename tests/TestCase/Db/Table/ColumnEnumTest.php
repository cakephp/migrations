<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db\Table;

use Migrations\Db\Table\Column;
use PHPUnit\Framework\TestCase;

class ColumnEnumTest extends TestCase
{
    /**
     * Test that enum values are included in toArray() output
     */
    public function testEnumValuesToArray(): void
    {
        $column = new Column();
        $column->setName('status');
        $column->setType('enum');
        $column->setValues(['active', 'inactive', 'pending']);

        $array = $column->toArray();

        $this->assertArrayHasKey('values', $array);
        $this->assertEquals(['active', 'inactive', 'pending'], $array['values']);
    }

    /**
     * Test that enum values can be set as string and are converted to array
     */
    public function testEnumValuesFromString(): void
    {
        $column = new Column();
        $column->setName('type');
        $column->setType('enum');
        $column->setValues('BSc, MSc, GRE, CV');

        $array = $column->toArray();

        $this->assertArrayHasKey('values', $array);
        $this->assertEquals(['BSc', 'MSc', 'GRE', 'CV'], $array['values']);
    }
}
