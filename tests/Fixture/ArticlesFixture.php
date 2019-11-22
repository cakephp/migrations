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
 * Class ArticlesFixture
 *
 */
class ArticlesFixture extends TestFixture
{

    /**
     * fields property
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'title' => ['type' => 'string', 'null' => true, 'length' => 255, 'comment' => 'Article title'],
        'category_id' => ['type' => 'integer', 'length' => 11],
        'product_id' => ['type' => 'integer', 'length' => 11],
        'note' => ['type' => 'string', 'default' => '7.4', 'length' => 255],
        'counter' => ['type' => 'integer', 'length' => 11, 'unsigned' => true],
        'active' => ['type' => 'boolean', 'default' => 0],
        'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        'modified' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        '_indexes' => [
            'title_idx' => [
                'type' => 'index',
                'columns' => ['title'],
            ],
        ],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'category_article_idx' => [
                'type' => 'foreign',
                'columns' => ['category_id'],
                'references' => ['categories', 'id'],
                'update' => 'noAction',
                'delete' => 'noAction',
            ],
            'product_idx' => [
                'type' => 'foreign',
                'columns' => ['product_id'],
                'references' => ['products', 'id'],
                'update' => 'cascade',
                'delete' => 'cascade',
            ],
        ],
    ];
}
