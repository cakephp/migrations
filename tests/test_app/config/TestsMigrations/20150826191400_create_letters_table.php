<?php
use Migrations\AbstractMigration;

class CreateLettersTable extends AbstractMigration
{
    public $autoId = false;

    public function change()
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
