<?php

use Migrations\Db\Table\Column;
use Migrations\BaseMigration;

class TrickyEdgeCase extends BaseMigration
{
    public function change()
    {
        $table = $this->table('user_logins');
        $table
            ->rename('just_logins')
            ->addColumn('thingy', Column::STRING, [
                'limit' => 12,
                'null' => true,
            ])
            ->addColumn('thingy2', Column::INTEGER)
            ->addIndex(['thingy'])
            ->save();
    }
}
