<?php

use Migrations\Db\Table\Column;
use Migrations\BaseMigration;

class UpdateInfoTable extends BaseMigration
{
    /**
     * Change.
     */
    public function change(): void
    {
        // info table
        $info = $this->table('info');
        $info->addColumn('password', Column::STRING, ['limit' => 40])
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
