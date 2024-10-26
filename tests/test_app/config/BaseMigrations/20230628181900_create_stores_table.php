<?php

use Migrations\BaseMigration;

class CreateStoresTable extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('stores', ['collation' => 'utf8_bin']);
        $table
            ->addColumn('name', 'string')
            ->addTimestamps()
            ->create();
    }
}
