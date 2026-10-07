<?php

use Cake\Database\Query;
use Migrations\Db\Table\Column;
use Migrations\BaseMigration;

class DirectionAwareReversibleDown extends BaseMigration
{
    public function change(): void
    {
        $this->table('change_direction_test')
            ->addColumn('subthing', Column::STRING, [
                'limit' => 12,
                'null' => true,
            ])
            ->update();

        if ($this->isMigratingUp()) {
            $query = $this->getQueryBuilder(Query::TYPE_UPDATE);
            $query
                ->update('change_direction_test')
                ->set(['subthing' => $query->identifier('thing')])
                ->where(['thing LIKE' => '%-%'])
                ->execute();
        } else {
            $this
                ->getQueryBuilder(Query::TYPE_UPDATE)
                ->update('change_direction_test')
                ->set(['subthing' => null])
                ->execute();
        }
    }
}
