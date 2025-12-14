<?php

use Migrations\BaseMigration;

class CreateTimestamptimezone extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('timestamp_articles');
        $table
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('created_at', 'timestamptimezone', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('deleted_at', 'datetimefractional', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ]);
        $table->create();
    }
}
