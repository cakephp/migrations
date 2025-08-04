<?php

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
