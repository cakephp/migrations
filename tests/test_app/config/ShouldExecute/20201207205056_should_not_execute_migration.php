<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ShouldNotExecuteMigration extends BaseMigration
{
    public function change(): void
    {
        // info table
        $this->table('info')->create();
    }

    public function shouldExecute(): bool
    {
        return false;
    }
}
