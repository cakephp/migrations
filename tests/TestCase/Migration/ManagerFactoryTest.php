<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase\Migration;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Datasource\ConnectionManager;
use Migrations\Migration\ManagerFactory;
use PHPUnit\Framework\TestCase;

class ManagerFactoryTest extends TestCase
{
    public function testConnection(): void
    {
        $this->out = new StubConsoleOutput();
        $this->out->setOutputAs(StubConsoleOutput::PLAIN);
        $this->in = new StubConsoleInput([]);

        $io = new ConsoleIo($this->out, $this->out, $this->in);

        $factory = new ManagerFactory(['connection' => 'test']);
        $result = $factory->createManager($io);

        $this->assertSame('test', $result->getConfig()->getConnection());
    }

    public function testDsnConnection(): void
    {
        $this->out = new StubConsoleOutput();
        $this->out->setOutputAs(StubConsoleOutput::PLAIN);
        $this->in = new StubConsoleInput([]);

        $io = new ConsoleIo($this->out, $this->out, $this->in);

        $factory = new ManagerFactory(['connection' => 'mysql://root@127.0.0.1/db_tmp']);
        $result = $factory->createManager($io);

        $this->assertSame('tmp', $result->getConfig()->getConnection());
        $expected = [
            'scheme' => 'mysql',
            'username' => 'root',
            'host' => '127.0.0.1',
            'className' => 'Cake\Database\Connection',
            'database' => 'db_tmp',
            'driver' => 'Cake\Database\Driver\Mysql',
            'name' => 'tmp',
        ];
        $this->assertEquals($expected, ConnectionManager::getConfig('tmp'));
    }
}
