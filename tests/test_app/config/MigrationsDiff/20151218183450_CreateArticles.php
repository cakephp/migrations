<?php
use Migrations\AbstractMigration;

class CreateArticles extends AbstractMigration
{
    public function change()
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
            ]);

        $table->addIndex([
            'rating',
        ], [
            'name' => 'rating_index',
            'unique' => false,
        ])
        ->create();
    }
}
