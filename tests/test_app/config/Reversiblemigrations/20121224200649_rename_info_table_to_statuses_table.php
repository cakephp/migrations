<?php

use Migrations\BaseMigration;

class RenameInfoTableToStatusesTable extends BaseMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // users table
        $table = $this->table('info');
        $table->rename('statuses')->save();
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
