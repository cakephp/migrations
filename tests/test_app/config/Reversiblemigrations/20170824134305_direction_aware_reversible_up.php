<?php

use Migrations\Db\Table\Column;
use Migrations\BaseMigration;

class DirectionAwareReversibleUp extends BaseMigration
{
    public function change(): void
    {
        $this->table('change_direction_test')
            ->addColumn('thing', Column::STRING, [
                'limit' => 12,
            ])
            ->create();

        if ($this->isMigratingUp()) {
            $this->table('change_direction_test')->insert([
                [
                    'thing' => 'one',
                ],
                [
                    'thing' => 'two',
                ],
                [
                    'thing' => 'fox-socks',
                ],
                [
                    'thing' => 'mouse-box',
                ],
            ])->save();
        }
    }
}
