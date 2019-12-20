<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase;

use Cake\Routing\Router;
use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use ConsoleIntegrationTestTrait;
    use StringCompareTrait;

    /**
     * @var string
     */
    protected $generatedFile = '';

    /**
     * @var array
     */
    protected $generatedFiles = [];

    public function setUp(): void
    {
        parent::setUp();

        Router::reload();
        $this->loadPlugins(['WyriHaximus/TwigView']);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        if ($this->generatedFile && file_exists($this->generatedFile)) {
            unlink($this->generatedFile);
            $this->generatedFile = '';
        }

        if (count($this->generatedFiles)) {
            foreach ($this->generatedFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            $this->generatedFiles = [];
        }
    }

    /**
     * Load a plugin from the tests folder, and add to the autoloader
     *
     * @param string $name plugin name to load
     * @return void
     */
    protected function _loadTestPlugin($name)
    {
        $root = dirname(dirname(__FILE__)) . DS;
        $path = $root . 'test_app' . DS . 'Plugin' . DS . $name . DS;

        $this->loadPlugins([
            $name => [
                'path' => $path,
            ],
        ]);
    }

    /**
     * Assert that a list of files exist.
     *
     * @param array $files The list of files to check.
     * @param string $message The message to use if a check fails.
     */
    protected function assertFilesExist(array $files, $message = '')
    {
        foreach ($files as $file) {
            $this->assertFileExists($file, $message);
        }
    }

    /**
     * Assert that a file contains a substring
     *
     * @param string $expected The expected content.
     * @param string $path The path to check.
     * @param string $message The error message.
     * @return void
     */
    protected function assertFileContains($expected, $path, $message = '')
    {
        $this->assertFileExists($path, 'Cannot test contents, file does not exist.');

        $contents = file_get_contents($path);
        $this->assertStringContainsString($expected, $contents, $message);
    }

    /**
     * Assert that a file does not contain a substring
     *
     * @param string $expected The expected content.
     * @param string $path The path to check.
     * @param string $message The error message.
     * @return void
     */
    protected function assertFileNotContains($expected, $path, $message = '')
    {
        $this->assertFileExists($path, 'Cannot test contents, file does not exist.');

        $contents = file_get_contents($path);
        $this->assertStringNotContainsString($expected, $contents, $message);
    }
}
