<?php

use Migrations\BaseMigration;

class CreateDropFkInitialSchema extends BaseMigration
{
    /**
     * Change.
     */
    public function change()
    {
        $this->table('my_table')
            ->addColumn('name', 'string')
            ->addColumn('entity_id', 'integer', ['signed' => false])
            ->create();

        $this->table('my_other_table')
            ->addColumn('name', 'string')
            ->create();
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
