<?php
use Migrations\AbstractMigration;

class TheDiff extends AbstractMigration
{

    public function up()
    {
        $this->table('articles')
            ->dropForeignKey(['user_id'])
            ->removeIndexByName('UNIQUE_SLUG')
            ->removeIndexByName('rating_index')
            ->removeIndexByName('BY_NAME')
            ->update();

        $this->table('articles')
            ->removeColumn('content')
            ->changeColumn('title', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->changeColumn('name', 'string', [
                'default' => null,
                'limit' => 10,
                'null' => false,
            ])
            ->update();

        $this->table('categories')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => 11,
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
                'after' => 'user_id',
                'default' => null,
                'length' => 11,
                'null' => false,
            ])
            ->addColumn('average_note', 'decimal', [
                'after' => 'category_id',
                'default' => null,
                'null' => true,
                'precision' => 5,
                'scale' => 5,
            ])
            ->addIndex(
                [
                    'slug',
                ],
                [
                    'name' => 'UNIQUE_SLUG',
                ]
            )
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
                    'update' => 'NO_ACTION',
                    'delete' => 'NO_ACTION'
                ]
            )
            ->update();

        $this->table('tags')->drop()->save();
    }

    public function down()
    {
        $this->table('categories')
            ->dropForeignKey(
                'user_id'
            )->save();

        $this->table('articles')
            ->dropForeignKey(
                'category_id'
            )->save();

        $this->table('tags')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->create();

        $this->table('articles')
            ->removeIndexByName('UNIQUE_SLUG')
            ->removeIndexByName('category_id')
            ->removeIndexByName('rating_index')
            ->update();

        $this->table('articles')
            ->addColumn('content', 'text', [
                'after' => 'rating',
                'default' => null,
                'length' => null,
                'null' => false,
            ])
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
            ->removeColumn('category_id')
            ->removeColumn('average_note')
            ->addIndex(
                [
                    'slug',
                ],
                [
                    'name' => 'UNIQUE_SLUG',
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
                    'name' => 'BY_NAME',
                ]
            )
            ->update();

        $this->table('articles')
            ->addForeignKey(
                'user_id',
                'users',
                'id',
                [
                    'update' => 'CASCADE',
                    'delete' => 'CASCADE'
                ]
            )
            ->update();

        $this->table('categories')->drop()->save();
    }
}

