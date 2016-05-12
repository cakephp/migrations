<?php
use Migrations\AbstractMigration;

class CreateArticles extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('articles');
        $table
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('excerpt', 'text', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('rating', 'integer', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('content', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $table->addIndex([
            'rating',
        ], [
            'name' => 'rating_index',
            'unique' => false,
        ])
        ->update();
    }

    public function down()
    {
        $this->dropTable('articles');
    }
}
