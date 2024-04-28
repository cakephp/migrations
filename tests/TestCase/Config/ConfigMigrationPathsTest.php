<?php

namespace Migrations\Test\TestCase\Config;

use Migrations\Config\Config;
use UnexpectedValueException;

/**
 * Class ConfigMigrationPathsTest
 */
class ConfigMigrationPathsTest extends AbstractConfigTestCase
{
    public function testGetMigrationPathsThrowsExceptionForNoPath()
    {
        $config = new Config([]);

        $this->expectException(UnexpectedValueException::class);

        $config->getMigrationPath();
    }

    /**
     * Normal behavior
     */
    public function testGetMigrationPaths()
    {
        $config = new Config($this->getConfigArray());
        $this->assertEquals($this->getMigrationPath(), $config->getMigrationPath());
    }
}
