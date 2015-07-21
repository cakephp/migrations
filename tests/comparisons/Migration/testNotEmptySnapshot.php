<?php
use Phinx\Migration\AbstractMigration;

class NotEmptySnapshot extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('articles');
        $table
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('category_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('created', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                [
                    'category_id',
                ]
            )
            ->create();

        $table = $this->table('categories');
        $table
            ->addColumn('parent_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('slug', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('created', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                [
                    'slug',
                ],
                ['unique' => true]
            )
            ->create();

        $table = $this->table('composite_pks', ['id' => false, 'primary_key' => ['id', 'name']]);
        $table
            ->addColumn('id', 'uuid', [
                'default' => '',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'default' => '',
                'limit' => 50,
                'null' => false,
            ])
            ->create();

        $table = $this->table('products');
        $table
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('slug', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('category_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('created', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                [
                    'slug',
                ],
                ['unique' => true]
            )
            ->addIndex(
                [
                    'category_id',
                ]
            )
            ->create();

        $table = $this->table('special_pks', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid', [
                'default' => '',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 256,
                'null' => true,
            ])
            ->create();

        $table = $this->table('special_tags');
        $table
            ->addColumn('article_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('author_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('tag_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('highlighted', 'boolean', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('highlighted_time', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                [
                    'article_id',
                ],
                ['unique' => true]
            )
            ->create();

        $table = $this->table('users');
        $table
            ->addColumn('username', 'string', [
                'default' => null,
                'limit' => 256,
                'null' => true,
            ])
            ->addColumn('password', 'string', [
                'default' => null,
                'limit' => 256,
                'null' => true,
            ])
            ->addColumn('created', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('updated', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('articles')
            ->addForeignKey(
                'category_id',
                'categories',
                'id',
                [
                    'update' => 'CASCADE',
                    'delete' => 'CASCADE'
                ]
            )
            ->update();

        $this->table('products')
            ->addForeignKey(
                'category_id',
                'categories',
                'id',
                [
                    'update' => 'CASCADE',
                    'delete' => 'CASCADE'
                ]
            )
            ->update();

    }

    public function down()
    {
        $this->table('articles')
            ->dropForeignKey(
                'category_id'
            )
            ->update();

        $this->table('products')
            ->dropForeignKey(
                'category_id'
            )
            ->update();

        $this->dropTable('articles');
        $this->dropTable('categories');
        $this->dropTable('composite_pks');
        $this->dropTable('products');
        $this->dropTable('special_pks');
        $this->dropTable('special_tags');
        $this->dropTable('users');
    }
}
