<?php

use Migrations\AbstractMigration;

class CreateStoresTable extends AbstractMigration
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
