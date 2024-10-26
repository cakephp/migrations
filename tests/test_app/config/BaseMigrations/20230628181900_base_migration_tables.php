<?php

use Migrations\BaseMigration;

class BaseMigrationTables extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('base_stores', ['collation' => 'utf8_bin']);
        $table
            ->addColumn('name', 'string')
            ->addTimestamps()
            ->addPrimaryKey('id')
            ->create();
        $io = $this->getIo();

        $res = $this->query('SELECT 121 as val');
        $io->out('query=' . $res->fetchColumn(0));
        $io->out('fetchRow=' . $this->fetchRow('SELECT 122 as val')['val']);
        $io->out('hasTable=' . $this->hasTable('base_stores'));

        // Run for coverage
        $this->getSelectBuilder();
        $this->getInsertBuilder();
        $this->getDeleteBuilder();
        $this->getUpdateBuilder();
    }
}
