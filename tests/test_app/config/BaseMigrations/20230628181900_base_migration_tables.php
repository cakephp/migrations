<?php

use Migrations\BaseMigration;

class BaseMigrationTables extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('base_stores', ['collation' => 'utf8_bin']);
        $table
            ->addColumn('name', 'string')
            ->addTimestamps()
            ->create();
    }
}
