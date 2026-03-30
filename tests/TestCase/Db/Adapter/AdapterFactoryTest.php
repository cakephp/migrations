<?php
declare(strict_types=1);

namespace Migrations\Test\Db\Adapter;

use Migrations\Db\Adapter\AbstractAdapter;
use Migrations\Db\Adapter\AdapterFactory;
use Migrations\Db\Adapter\AdapterInterface;
use Migrations\Db\Adapter\MysqlAdapter;
use Migrations\Db\Adapter\TimedOutputAdapter;
use Migrations\Test\TestCase\Db\Adapter\DefaultAdapterTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class AdapterFactoryTest extends TestCase
{
    private AdapterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = AdapterFactory::instance();
    }

    protected function tearDown(): void
    {
        unset($this->factory);
    }

    public function testInstanceIsFactory(): void
    {
        $this->assertInstanceOf(AdapterFactory::class, $this->factory);
    }

    public function testRegisterAdapter(): void
    {
        $pdo = new class (['foo' => 'bar']) extends AbstractAdapter {
            use DefaultAdapterTrait;
        };
        $this->factory->registerAdapter('test', function (array $options) use ($pdo): object {
            $this->assertEquals('value', $options['key']);

            return $pdo;
        });

        $this->assertEquals($pdo, $this->factory->getAdapter('test', ['key' => 'value']));
    }

    public function testRegisterAdapterFailure(): void
    {
        $adapter = static::class;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Adapter class `Migrations\Test\Db\Adapter\AdapterFactoryTest` must implement `Migrations\Db\Adapter\AdapterInterface`');

        $this->factory->registerAdapter('test', $adapter);
    }

    public function testGetAdapter(): void
    {
        $adapter = $this->factory->getAdapter('mysql', []);

        $this->assertInstanceOf(MysqlAdapter::class, $adapter);
    }

    public function testGetAdapterFailure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Adapter "bad" has not been registered');

        $this->factory->getAdapter('bad', []);
    }

    public function testRegisterWrapper(): void
    {
        // WrapperFactory::getClass is protected, work around it to avoid
        // creating unnecessary instances and making the test more complex.
        $method = new ReflectionMethod($this->factory::class, 'getWrapperClass');

        $wrapper = $method->invoke($this->factory, 'record');
        $this->factory->registerWrapper('test', $wrapper);

        $this->assertEquals($wrapper, $method->invoke($this->factory, 'test'));
    }

    public function testRegisterWrapperFailure(): void
    {
        $wrapper = static::class;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wrapper class `Migrations\Test\Db\Adapter\AdapterFactoryTest` must implement `Migrations\Db\Adapter\WrapperInterface`');

        $this->factory->registerWrapper('test', $wrapper);
    }

    private function getAdapterMock(): MockObject
    {
        return $this->getMockBuilder(AdapterInterface::class)->getMock();
    }

    public function testGetWrapper(): void
    {
        $wrapper = $this->factory->getWrapper('timed', $this->getAdapterMock());

        $this->assertInstanceOf(TimedOutputAdapter::class, $wrapper);
    }

    public function testGetWrapperFailure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wrapper "nope" has not been registered');

        $this->factory->getWrapper('nope', $this->getAdapterMock());
    }
}
