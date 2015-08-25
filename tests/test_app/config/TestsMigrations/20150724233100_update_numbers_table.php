<?php
use Migrations\AbstractMigration;

class UpdateNumbersTable extends AbstractMigration
{

    public function up()
    {
        $table = $this->table('numbers');
        $table
            ->addColumn('radix', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->update();
    }

    public function down()
    {
        $table = $this->table('numbers');
        $table
            ->removeColumn('radix')
            ->update();
    }
}
