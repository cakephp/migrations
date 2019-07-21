<?php
namespace TestBlog\Model\Table;

use Cake\ORM\Table;

/**
 * Articles Model
 *
 */
class SpecialTagsTable extends Table
{
    public function initialize(array $config): void
    {
        $this->setTable('alternative.special_tags');
    }
}
