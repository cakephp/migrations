<?php

use Migrations\AbstractMigration;

class CreateLettersTable extends AbstractMigration
{
    public bool $autoId = false;

    public function change(): void
    {
        $table = $this->table('letters');
        $table
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('letter', 'string', [
                'limit' => 1,
            ])
            ->create();
    }
}
