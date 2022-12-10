<?php

use Migrations\AbstractMigration;

class UpdateNumbersTable extends AbstractMigration
{
    public function up(): void
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

    public function down(): void
    {
        $table = $this->table('numbers');
        $table
            ->removeColumn('radix')
            ->update();
    }
}
