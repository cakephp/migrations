<?php

use Migrations\BaseMigration;

class RenameBioToBiography extends BaseMigration
{
    /**
     * Change.
     */
    public function change()
    {
        // users table
        $table = $this->table('users');
        $table->renameColumn('bio', 'biography')->save();
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
