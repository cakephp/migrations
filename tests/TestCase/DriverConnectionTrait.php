<?php
declare(strict_types=1);

namespace Migrations\Test\TestCase;

use Cake\Database\Driver;
use PDO;
use ReflectionProperty;

trait DriverConnectionTrait
{
    protected function getDriverConnection(Driver $driver): PDO
    {
        $prop = new ReflectionProperty($driver, 'pdo');

        return $prop->getValue($driver);
    }

    protected function setDriverConnection(Driver $driver, PDO $connection): void
    {
        $prop = new ReflectionProperty($driver, 'pdo');
        $prop->setValue($driver, $connection);
    }
}
