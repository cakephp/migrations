<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class ChangeTestTable extends BaseMigration
{
    public function change(): void
    {
        $this->table('test')
            ->addColumn('name', 'string')
            ->save();
    }
}
