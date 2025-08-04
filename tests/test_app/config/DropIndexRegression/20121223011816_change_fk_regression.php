<?php

use Migrations\BaseMigration;

class ChangeFkRegression extends BaseMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $table = $this->table('my_table');
        $table
            ->dropForeignKey('entity_id')
            ->addForeignKey('entity_id', 'my_other_table', 'id', [
                'constraint' => 'my_other_table_foreign_key',
            ])
            ->update();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
    }
}
