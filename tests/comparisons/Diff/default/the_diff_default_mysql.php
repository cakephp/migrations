<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class TheDiffDefaultMysql extends AbstractMigration
{
    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-up-method
     * @return void
     */
    public function up(): void
    {
        $this->table('articles')
            ->dropForeignKey([], 'articles_ibfk_1')
            ->removeIndexByName('UNIQUE_SLUG')
            ->removeIndexByName('rating_index')
            ->removeIndexByName('BY_NAME')
            ->update();

        $this->table('articles')
            ->removeColumn('content')
            ->changeColumn('id', 'integer', [
                'default' => null,
                'length' => null,
                'limit' => null,
                'null' => false,
                'signed' => true,
            ])
            ->changeColumn('title', 'text', [
                'default' => null,
                'length' => null,
                'limit' => null,
                'null' => false,
            ])
            ->changeColumn('rating', 'integer', [
                'default' => null,
                'length' => null,
                'limit' => null,
                'null' => false,
            ])
            ->changeColumn('name', 'string', [
                'default' => null,
                'limit' => 10,
                'null' => false,
            ])
            ->changeColumn('user_id', 'integer', [
                'default' => null,
                'length' => null,
                'limit' => null,
                'null' => false,
                'signed' => true,
            ])
            ->update();

        $this->table('users')
            ->changeColumn('id', 'integer', [
                'default' => null,
                'length' => null,
                'limit' => null,
                'null' => false,
                'signed' => true,
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
                'limit' => null,
                'null' => false,
                'signed' => true,
            ])
            ->addIndex(
                [
                    'user_id',
                ],
                [
                    'name' => 'categories_ibfk_1',
                ]
            )
            ->addIndex(
                [
                    'name',
                ],
                [
                    'name' => 'name',
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
                    'delete' => 'RESTRICT',
                    'constraint' => 'categories_ibfk_1'
                ]
            )
            ->update();

        $this->table('articles')
            ->addColumn('category_id', 'integer', [
                'after' => 'user_id',
                'default' => null,
                'length' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('average_note', 'decimal', [
                'after' => 'category_id',
                'default' => null,
                'null' => true,
                'precision' => 5,
                'scale' => 5,
                'signed' => true,
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
                    'delete' => 'NO_ACTION',
                    'constraint' => 'articles_ibfk_1'
                ]
            )
            ->update();

        $this->table('tags')->drop()->save();
    }

    /**
     * Down Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-down-method
     * @return void
     */
    public function down(): void
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
            ->changeColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'length' => 11,
                'null' => false,
            ])
            ->changeColumn('title', 'string', [
                'default' => null,
                'length' => 255,
                'null' => false,
            ])
            ->changeColumn('rating', 'integer', [
                'default' => null,
                'length' => 11,
                'null' => false,
            ])
            ->changeColumn('name', 'string', [
                'default' => null,
                'length' => 255,
                'null' => false,
            ])
            ->changeColumn('user_id', 'integer', [
                'default' => null,
                'length' => 11,
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

        $this->table('users')
            ->changeColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'length' => 11,
                'null' => false,
            ])
            ->update();

        $this->table('articles')
            ->addForeignKey(
                'user_id',
                'users',
                'id',
                [
                    'update' => 'CASCADE',
                    'delete' => 'CASCADE',
                    'constraint' => 'articles_ibfk_1'
                ]
            )
            ->update();

        $this->table('categories')->drop()->save();
    }
}
