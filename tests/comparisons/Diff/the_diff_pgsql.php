<?php
use Migrations\AbstractMigration;

class TheDiffPgsql extends AbstractMigration
{

    public function up()
    {
        $this->table('articles')
            ->dropForeignKey([], 'articles_user_id')
            ->removeIndexByName('unique_slug')
            ->removeIndexByName('rating_index')
            ->removeIndexByName('by_name')
            ->update();

        $this->table('articles')
            ->removeColumn('content')
            ->changeColumn('title', 'text')
            ->changeColumn('name', 'string', [
                'length' => 50,
            ])
            ->update();

        $table = $this->table('categories');
        $table
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => false,
            ])
            ->addIndex(
                [
                    'user_id',
                ]
            )
            ->addIndex(
                [
                    'name',
                ]
            )
            ->create();

        $this->table('categories')
            ->addForeignKey(
                'user_id',
                'users',
                'id',
                [
                    'update' => 'RESTRICT',
                    'delete' => 'RESTRICT'
                ]
            )
            ->update();

        $this->table('articles')
            ->addColumn('category_id', 'integer', [
                'default' => null,
                'length' => 10,
                'null' => false,
            ])
            ->update();

        $this->table('articles')
            ->addIndex(
                [
                    'category_id',
                ],
                [
                    'name' => 'category_id',
                ]
            )
            ->addIndex(
                [
                    'slug',
                ],
                [
                    'name' => 'unique_slug',
                ]
            )
            ->addIndex(
                [
                    'name',
                ],
                [
                    'name' => 'rating_index',
                ]
            )
            ->update();

        $this->table('articles')
            ->addForeignKey(
                'category_id',
                'categories',
                'id',
                [
                    'update' => 'restrict',
                    'delete' => 'restrict'
                ]
            )
            ->update();

            $this->dropTable('tags');
    }


    public function down()
    {
        $this->table('categories')
            ->dropForeignKey(
                'user_id'
            );
        $this->table('articles')
            ->dropForeignKey(
                'category_id'
            );

            $table = $this->table('tags');
            $table
                ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->create();


        $this->table('articles')
            ->removeIndexByName('category_id')
            ->removeIndexByName('unique_slug')
            ->removeIndexByName('rating_index')
            ->update();

        $this->table('articles')
            ->addIndex(
                [
                    'slug',
                ],
                [
                    'name' => 'unique_slug',
                    'unique' => true,
                ]
            )
            ->addIndex(
                [
                    'rating',
                ],
                [
                    'name' => 'rating_index',
                ]
            )
            ->addIndex(
                [
                    'name',
                ],
                [
                    'name' => 'by_name',
                ]
            )
            ->update();

        $this->table('articles')
            ->addColumn('content', 'text', [
                'default' => null,
                'length' => null,
                'null' => false,
            ])
            ->update();

        $this->table('articles')
            ->changeColumn('title', 'string', [
                'default' => null,
                'length' => 255,
                'null' => false,
            ])
            ->changeColumn('name', 'string', [
                'default' => null,
                'length' => 255,
                'null' => false,
            ])
            ->update();

        $this->table('articles')
            ->removeColumn('category_id')
            ->update();

        $this->dropTable('categories');
    }
}
