<?php
use Migrations\AbstractMigration;

class CreateNumbersTable extends AbstractMigration
{

    public function change()
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
