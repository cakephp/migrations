<?php

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR2R.Classes.ClassFileName.NoMatch
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
