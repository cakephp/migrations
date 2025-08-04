<?php

use Migrations\BaseMigration;

class CreateArticlesAddRemove extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('articles');
        $table
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('excerpt', 'text', [
                'default' => null,
                'null' => false,
            ])
            ->create();
    }
}
