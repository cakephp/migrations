<?php

use Migrations\AbstractMigration;

class CreateArticlesWithAutoIdCompatibleSignedPrimaryKeys extends AbstractMigration
{
    public function change(): void
    {
        $this->table('articles')->create();
    }
}
