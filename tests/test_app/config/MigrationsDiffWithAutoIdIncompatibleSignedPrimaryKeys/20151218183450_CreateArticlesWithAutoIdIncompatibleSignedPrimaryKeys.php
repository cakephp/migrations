<?php

use Migrations\AbstractMigration;

class CreateArticlesWithAutoIdIncompatibleSignedPrimaryKeys extends AbstractMigration
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
                'signed' => true,
            ])
            ->addPrimaryKey(['id'])
            ->create();
    }
}
