<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Db;

use Migrations\Db\Literal;
use PHPUnit\Framework\TestCase;

class LiteralTest extends TestCase
{
    public function testToString(): void
    {
        $str = 'test1';
        $instance = new Literal($str);
        $this->assertEquals($str, (string)$instance);
    }

    public function testFrom(): void
    {
        $str = 'test1';
        $instance = Literal::from($str);
        $this->assertInstanceOf(Literal::class, $instance);
        $this->assertEquals($str, (string)$instance);
    }
}
