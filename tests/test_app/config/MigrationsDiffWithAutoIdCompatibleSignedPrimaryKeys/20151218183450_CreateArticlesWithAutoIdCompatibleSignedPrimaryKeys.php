<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateArticlesWithAutoIdCompatibleSignedPrimaryKeys extends BaseMigration
{
    public function change(): void
    {
        $this->table('articles')->create();
    }
}
