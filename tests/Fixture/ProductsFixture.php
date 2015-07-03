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
 * Class CategoriesFixture
 *
 */
class ProductsFixture extends TestFixture
{

    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'title' => ['type' => 'string', 'null' => true, 'length' => 255],
        'slug' => ['type' => 'string', 'null' => true, 'length' => 255],
        'category_id' => ['type' => 'integer', 'length' => 11],
        'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        'modified' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'UNIQUE_SLUG' => ['type' => 'unique', 'columns' => ['slug']],
            'category_idx' => [
                'type' => 'foreign',
                'columns' => ['category_id'],
                'references' => ['categories', 'id'],
                'update' => 'cascade',
                'delete' => 'cascade'
            ]
        ]
    ];
}
