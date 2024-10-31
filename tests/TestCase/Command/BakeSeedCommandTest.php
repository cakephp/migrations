<?php
declare(strict_types=1);

/**
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase\Command;

use Cake\Console\BaseCommand;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\TestSuite\StringCompareTrait;
use Migrations\Test\TestCase\TestCase;

/**
 * BakeSeedCommandTest class
 */
class BakeSeedCommandTest extends TestCase
{
    use StringCompareTrait;

    /**
     * @var string[]
     */
    protected array $fixtures = [
        'plugin.Migrations.Events',
        'plugin.Migrations.Texts',
    ];

    /**
     * ConsoleIo mock
     *
     * @var \Cake\Console\ConsoleIo|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $io;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Seeds' . DS;
    }

    /**
     * Test empty migration with phinx base class.
     *
     * @return void
     */
    public function testBasicBakingPhinx()
    {
        Configure::write('Migrations.backend', 'phinx');
        $this->generatedFile = ROOT . DS . 'config/Seeds/ArticlesSeed.php';
        $this->exec('bake seed Articles --connection test');

        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $result = file_get_contents($this->generatedFile);
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }

    /**
     * Test empty migration.
     *
     * @return void
     */
    public function testBasicBaking()
    {
        $this->generatedFile = ROOT . DS . 'config/Seeds/ArticlesSeed.php';
        $this->exec('bake seed Articles --connection test');

        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $result = file_get_contents($this->generatedFile);
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }

    /**
     * Test with data, all fields, no limit
     *
     * @return void
     */
    public function testWithData()
    {
        $this->generatedFile = ROOT . DS . 'config/Seeds/EventsSeed.php';
        $this->exec('bake seed Events --connection test --data');

        $path = __FUNCTION__ . '.php';
        if (in_array(getenv('DB'), ['pgsql', 'sqlserver'])) {
            $path = getenv('DB') . DS . $path;
        } elseif (PHP_VERSION_ID >= 80100) {
            $path = 'php81' . DS . $path;
        }

        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $result = file_get_contents($this->generatedFile);
        $this->assertSameAsFile($path, $result);
    }

    /**
     * Test with data and fields specified
     *
     * @return void
     */
    public function testWithDataAndFields()
    {
        $this->generatedFile = ROOT . DS . 'config/Seeds/EventsSeed.php';
        $this->exec('bake seed Events --connection test --data --fields title,description');

        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $result = file_get_contents($this->generatedFile);
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }

    /**
     * Test with data and limit specified
     *
     * @return void
     */
    public function testWithDataAndLimit()
    {
        $this->generatedFile = ROOT . DS . 'config/Seeds/EventsSeed.php';
        $this->exec('bake seed Events --connection test --data --limit 2');

        $path = __FUNCTION__ . '.php';
        if (in_array(getenv('DB'), ['pgsql', 'sqlserver'])) {
            $path = getenv('DB') . DS . $path;
        } elseif (PHP_VERSION_ID >= 80100) {
            $path = 'php81' . DS . $path;
        }

        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $result = file_get_contents($this->generatedFile);
        $this->assertSameAsFile($path, $result);
    }

    /**
     * Test prettifyArray method. Texts fixture contains bunch of values trying to confuse prettifyArray
     *
     * @return void
     */
    public function testPrettifyArray()
    {
        $this->generatedFile = ROOT . DS . 'config/Seeds/TextsSeed.php';
        $this->exec('bake seed Texts --connection test --data');

        $this->assertExitCode(BaseCommand::CODE_SUCCESS);
        $result = file_get_contents($this->generatedFile);
        $this->assertSameAsFile(__FUNCTION__ . '.php', $result);
    }
}
