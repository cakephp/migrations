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
        $this->setTable('cakephp_test.categories');
    }
}
