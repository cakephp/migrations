<?php

use Migrations\BaseMigration;

class Migrator extends BaseMigration
{
    public function up()
    {
        $this->table('migrator')->addColumn('test', 'integer')->create();
        $this->table('migrator')->insert(['test' => 1])->save();
    }

    public function down()
    {
    }
}
