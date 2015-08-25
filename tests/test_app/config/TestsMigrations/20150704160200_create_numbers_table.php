<?php
use Migrations\AbstractMigration;

class CreateNumbersTable extends AbstractMigration
{

    public function up()
    {
        $table = $this->table('numbers');
        $table
            ->addColumn('number', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->create();
    }

    public function down()
    {
        $this->dropTable('numbers');
    }
}
