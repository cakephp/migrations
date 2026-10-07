<?php

use Migrations\BaseMigration;

class AddRegressionDropFk extends BaseMigration
{
    /**
     * Change.
     */
    public function change(): void
    {
        $table = $this->table('my_table');
        $table
            ->addForeignKey('entity_id', 'my_other_table', 'id', [
                'constraint' => 'my_other_table_foreign_key',
            ])
            ->addIndex(['entity_id'], ['unique' => true])
            ->update();
    }

    /**
     * Migrate Up.
     */
    public function up()
    {
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
    }
}
