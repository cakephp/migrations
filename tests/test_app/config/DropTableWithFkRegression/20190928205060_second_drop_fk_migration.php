<?php

use Migrations\BaseMigration;

class SecondDropFkMigration extends BaseMigration
{
    public function change(): void
    {
        $this->table('customers')
            ->addColumn('name', 'text')
            ->create();

        $this->table('orders')
            ->addColumn('customer_id', 'integer', ['signed' => false])
            ->addForeignKey('customer_id', 'customers', 'id')
            ->update();
    }
}
