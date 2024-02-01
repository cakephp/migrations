<?php

namespace Migrations\Test\TestCase\Config;

use Migrations\Config\Config;
use UnexpectedValueException;

/**
 * Class ConfigSeedPathsTest
 */
class ConfigSeedPathsTest extends AbstractConfigTest
{
    public function testGetSeedPathsThrowsExceptionForNoPath()
    {
        $config = new Config([]);

        $this->expectException(UnexpectedValueException::class);

        $config->getSeedPaths();
    }

    /**
     * Normal behavior
     */
    public function testGetSeedPaths()
    {
        $config = new Config($this->getConfigArray());
        $this->assertEquals($this->getSeedPaths(), $config->getSeedPaths());
    }

    public function testGetSeedPathConvertsStringToArray()
    {
        $values = [
            'paths' => [
                'seeds' => '/test',
            ],
        ];

        $config = new Config($values);
        $paths = $config->getSeedPaths();

        $this->assertIsArray($paths);
        $this->assertCount(1, $paths);
    }
}
