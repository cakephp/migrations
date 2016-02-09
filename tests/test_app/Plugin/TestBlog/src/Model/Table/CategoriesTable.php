<?php
namespace TestBlog\Model\Table;

use Cake\ORM\Table;

/**
 * Articles Model
 *
 */
class CategoriesTable extends Table
{
    public function initialize(array $config)
    {
        $db = getenv('DB');
        switch($db) {
            case 'sqlite':
                $dbName = ':memory:.';
                break;
            case 'mysql':
            case 'pgsql':
                $dbName = 'cakephp_test.';
                break;
        }
        $dbName .= 'categories';
        $this->table($dbName);
    }
}
