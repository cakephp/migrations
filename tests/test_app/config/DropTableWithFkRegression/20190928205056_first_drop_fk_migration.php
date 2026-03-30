<?php

use Migrations\BaseMigration;

class FirstDropFkMigration extends BaseMigration
{
    public function change(): void
    {
        $this->table('orders')
            ->addColumn('order_date', 'timestamp')
            ->create();
    }
}
