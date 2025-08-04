<?php

use Migrations\BaseMigration;

class UpdateNumbersTable extends BaseMigration
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
