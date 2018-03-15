<?php
namespace TestBlog\Model\Table;

use Cake\ORM\Table;

/**
 * Articles Model
 *
 */
class SpecialTagsTable extends Table
{
    public function initialize(array $config)
    {
        $this->setTable('alternative.special_tags');
    }
}
