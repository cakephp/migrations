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
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Class SpecialPkFixture
 *
 */
class SpecialPkFixture extends TestFixture
{
    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'uuid', 'default' => 'a4950df3-515f-474c-be4c-6a027c1957e7'],
        'name' => ['type' => 'string', 'null' => true, 'length' => 256],
        '_constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ];
}
