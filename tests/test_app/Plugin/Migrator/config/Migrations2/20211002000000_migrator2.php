<?php

use Migrations\BaseMigration;

class Migrator2 extends BaseMigration
{
    public function up(): void
    {
        $this->table('migrator')->insert(['test' => 2])->save();
    }

    public function down()
    {
    }
}
