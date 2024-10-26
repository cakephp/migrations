<?php

use Migrations\BaseMigration;

class CreateNumbersTable extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('numbers', ['collation' => 'utf8_bin']);
        $table
            ->addColumn('number', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->create();
    }
}
