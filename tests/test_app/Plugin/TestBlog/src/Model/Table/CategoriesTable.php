<?php
declare(strict_types=1);

namespace TestBlog\Model\Table;

use Cake\ORM\Table;

/**
 * Articles Model
 */
class CategoriesTable extends Table
{
    public function initialize(array $config): void
    {
        $this->setTable('cakephp_test.categories');
    }
}
