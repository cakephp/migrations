<?php

namespace Migrations\Test\TestCase\Config;

use Migrations\Config\Config;
use UnexpectedValueException;

/**
 * Class ConfigSeedPathsTest
 */
class ConfigSeedPathsTest extends AbstractConfigTestCase
{
    public function testGetSeedPathsThrowsExceptionForNoPath()
    {
        $config = new Config([]);

        $this->expectException(UnexpectedValueException::class);

        $config->getSeedPath();
    }

    /**
     * Normal behavior
     */
    public function testGetSeedPaths()
    {
        $config = new Config($this->getConfigArray());
        $this->assertEquals($this->getSeedPath(), $config->getSeedPath());
    }

    public function testGetSeedPathConvertsStringToArray()
    {
        $values = [
            'paths' => [
                'seeds' => '/test',
            ],
        ];

        $config = new Config($values);
        $path = $config->getSeedPath();
        $this->assertEquals('/test', $path);
    }
}
