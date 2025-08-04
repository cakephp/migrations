<?php

use Migrations\BaseMigration;

class ChangeColumnRegression extends BaseMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $table = $this->table('my_table');
        $table
            ->renameColumn('name', 'title')
            ->changeColumn('title', 'text')
            ->update();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
    }
}
