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

use Cake\Database\Driver\Mysql;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\TestFixture;
use PDO;

/**
 * Class ProductsFixture
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
        'slug' => ['type' => 'string', 'null' => true, 'length' => 100],
        'category_id' => ['type' => 'integer', 'length' => 11],
        'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        'modified' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        '_indexes' => [
            'title_idx_ft' => [
                'type' => 'index',
                'columns' => ['title'],
            ],
        ],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'products_unique_slug' => ['type' => 'unique', 'columns' => ['slug']],
            'products_category_unique' => ['type' => 'unique', 'columns' => ['category_id', 'id']],
            'category_idx' => [
                'type' => 'foreign',
                'columns' => ['category_id'],
                'references' => ['categories', 'id'],
                'update' => 'cascade',
                'delete' => 'cascade',
            ],
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $connection = ConnectionManager::get($this->connection());
        $driver = $connection->getDriver();

        if ($driver instanceof Mysql) {
            $dbv = getenv('DBV');
            if ($dbv === '56') {
                $this->fields['_indexes']['title_idx_ft']['type'] = 'fulltext';
            }
        }

        parent::init();
    }
}
