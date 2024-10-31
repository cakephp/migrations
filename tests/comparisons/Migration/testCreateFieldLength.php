<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateUsers extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('users');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 128,
            'null' => false,
        ]);
        $table->addColumn('counter', 'integer', [
            'default' => null,
            'limit' => 8,
            'null' => false,
        ]);
        $table->create();
    }
}
