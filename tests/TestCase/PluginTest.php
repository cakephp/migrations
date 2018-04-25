<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         1.8.2
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase;

use Migrations\Plugin;
use Cake\Routing\RouteBuilder;
use Cake\Routing\RouteCollection;
use Cake\TestSuite\TestCase;

/**
 * PluginTest class
 *
 */
class PluginTest extends TestCase
{
    public function testRoutes()
    {
        $collection = new RouteCollection();
        $routes = new RouteBuilder($collection, '/');

        $plugin = new Plugin();
        $this->assertNull($plugin->routes($routes));
        $this->assertCount(0, $collection->routes());
    }
}
