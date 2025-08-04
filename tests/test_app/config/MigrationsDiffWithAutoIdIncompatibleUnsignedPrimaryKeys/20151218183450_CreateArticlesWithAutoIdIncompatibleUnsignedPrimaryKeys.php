<?php

use Migrations\BaseMigration;

class CreateArticlesWithAutoIdIncompatibleUnsignedPrimaryKeys extends BaseMigration
{
    public bool $autoId = false;

    public function change(): void
    {
        $this->table('articles')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addPrimaryKey(['id'])
            ->create();
    }
}
