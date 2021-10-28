<?php

use Migrations\AbstractMigration;

class Migrator2 extends AbstractMigration
{
    public function up()
    {
        $this->table('migrator')->insert(['test' => 2])->save();
    }

    public function down()
    {
    }
}
