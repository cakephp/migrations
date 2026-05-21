<?php

use Migrations\BaseMigration;

class AlterArticlesDefault extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/guides/writing-migrations/migration-methods.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('articles');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addIndex([
            'name',
        ], [
            'name' => 'BY_NAME',
            'unique' => false,
        ]);
        $table->update();
    }
}
