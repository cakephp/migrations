<?php

use Migrations\BaseMigration;

class CreateArticlesWithAutoIdCompatibleSignedPrimaryKeys extends BaseMigration
{
    public function change(): void
    {
        $this->table('articles')->create();
    }
}
