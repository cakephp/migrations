<?php
use Migrations\AbstractMigration;

class TestNotEmptySnapshotSqlite extends AbstractMigration
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
            ->addColumn('product_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('counter', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('active', 'boolean', [
                'default' => false,
                'limit' => null,
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
                ],
                [
                    'name' => 'category_id_fk',
                ]
            )
            ->addIndex(
                [
                    'product_id',
                ],
                [
                    'name' => 'product_id_fk',
                ]
            )
            ->addIndex(
                [
                    'title',
                ],
                [
                    'name' => 'title_idx',
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
                [
                    'name' => 'sqlite_autoindex_categories_1',
                    'unique' => true,
                ]
            )
            ->create();

        $table = $this->table('composite_pks', ['id' => false, 'primary_key' => ['id', 'name']]);
        $table
            ->addColumn('id', 'uuid', [
                'default' => 'a4950df3-515f-474c-be4c-6a027c1957e7',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'default' => '',
                'limit' => 50,
                'null' => false,
            ])
            ->create();

        $table = $this->table('orders');
        $table
            ->addColumn('product_category', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('product_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addIndex(
                [
                    'product_category',
                    'product_id',
                ],
                [
                    'name' => 'product_category_fk',
                ]
            )
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
                [
                    'name' => 'sqlite_autoindex_products_1',
                    'unique' => true,
                ]
            )
            ->addIndex(
                [
                    'category_id',
                    'id',
                ],
                [
                    'name' => 'sqlite_autoindex_products_2',
                    'unique' => true,
                ]
            )
            ->addIndex(
                [
                    'category_id',
                ],
                [
                    'name' => 'category_id_fk',
                ]
            )
            ->addIndex(
                [
                    'title',
                ],
                [
                    'name' => 'title_idx_ft',
                ]
            )
            ->create();

        $table = $this->table('special_pks', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid', [
                'default' => 'a4950df3-515f-474c-be4c-6a027c1957e7',
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
                [
                    'name' => 'sqlite_autoindex_special_tags_1',
                    'unique' => true,
                ]
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
            ->addForeignKey(
                'product_id',
                'products',
                'id',
                [
                    'update' => 'CASCADE',
                    'delete' => 'CASCADE'
                ]
            )
            ->update();

        $this->table('orders')
            ->addForeignKey(
                [
                    'product_category',
                    'product_id',
                ],
                'products',
                [
                    'category_id',
                    'id',
                ],
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
            ->dropForeignKey(
                'product_id'
            );

        $this->table('orders')
            ->dropForeignKey(
                [
                    'product_category',
                    'product_id',
                ]
            );

        $this->table('products')
            ->dropForeignKey(
                'category_id'
            );

        $this->dropTable('articles');
        $this->dropTable('categories');
        $this->dropTable('composite_pks');
        $this->dropTable('orders');
        $this->dropTable('products');
        $this->dropTable('special_pks');
        $this->dropTable('special_tags');
        $this->dropTable('users');
    }
}
