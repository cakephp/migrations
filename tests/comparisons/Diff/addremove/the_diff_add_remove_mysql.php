<?php
use Migrations\AbstractMigration;

class TheDiffAddRemoveMysql extends AbstractMigration
{

    public function up()
    {

        $this->table('articles')
            ->removeColumn('excerpt')
            ->update();

        $this->table('articles')
            ->addColumn('the_text', 'text', [
                'after' => 'title',
                'default' => null,
                'length' => null,
                'null' => false,
            ])
            ->update();
    }

    public function down()
    {

        $this->table('articles')
            ->addColumn('excerpt', 'text', [
                'after' => 'title',
                'default' => null,
                'length' => null,
                'null' => false,
            ])
            ->removeColumn('the_text')
            ->update();
    }
}

