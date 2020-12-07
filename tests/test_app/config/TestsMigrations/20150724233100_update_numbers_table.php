<?php

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR2R.Classes.ClassFileName.NoMatch
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
