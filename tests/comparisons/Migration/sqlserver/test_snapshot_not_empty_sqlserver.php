<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class TestSnapshotNotEmptySqlserver extends BaseMigration
{
    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/en/migrations.html#the-up-method
     * @return void
     */
    public function up(): void
    {
        $this->table('articles')
            ->addColumn('title', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'comment' => 'Article title',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('category_id', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => true,
            ])
            ->addColumn('product_id', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => true,
            ])
            ->addColumn('note', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => '7.4',
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('counter', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => true,
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
                'precision' => 7,
                'scale' => 7,
            ])
            ->addColumn('modified', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->addIndex(
                $this->index('title')
                    ->setName('articles_title_idx')
            )
            ->create();

        $this->table('categories')
            ->addColumn('parent_id', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => true,
            ])
            ->addColumn('title', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('slug', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => 100,
                'null' => true,
            ])
            ->addColumn('created', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->addColumn('modified', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->addIndex(
                $this->index('slug')
                    ->setName('categories_slug_unique')
                    ->setType('unique')
            )
            ->create();

        $this->table('composite_pks', ['id' => false, 'primary_key' => ['id', 'name']])
            ->addColumn('id', 'uuid', [
                'default' => 'a4950df3-515f-474c-be4c-6a027c1957e7',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => '',
                'limit' => 10,
                'null' => false,
            ])
            ->create();

        $this->table('events')
            ->addColumn('title', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('description', 'text', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('published', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => 'N',
                'limit' => 1,
                'null' => true,
            ])
            ->create();

        $this->table('orders')
            ->addColumn('product_category', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => false,
            ])
            ->addColumn('product_id', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => false,
            ])
            ->addIndex(
                $this->index([
                        'product_category',
                        'product_id',
                    ])
                    ->setName('orders_product_category_idx')
            )
            ->create();

        $this->table('parts')
            ->addColumn('name', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('number', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => true,
            ])
            ->create();

        $this->table('products')
            ->addColumn('title', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('slug', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => 100,
                'null' => true,
            ])
            ->addColumn('category_id', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => true,
            ])
            ->addColumn('created', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->addColumn('modified', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->addIndex(
                $this->index([
                        'category_id',
                        'id',
                    ])
                    ->setName('products_category_unique')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('slug')
                    ->setName('products_slug_unique')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('title')
                    ->setName('products_title_idx')
            )
            ->create();

        $this->table('special_pks', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid', [
                'default' => 'a4950df3-515f-474c-be4c-6a027c1957e7',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => 256,
                'null' => true,
            ])
            ->create();

        $this->table('special_tags')
            ->addColumn('article_id', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => false,
            ])
            ->addColumn('author_id', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => true,
            ])
            ->addColumn('tag_id', 'integer', [
                'default' => null,
                'limit' => 10,
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
                'precision' => 7,
                'scale' => 7,
            ])
            ->addIndex(
                $this->index('article_id')
                    ->setName('special_tags_article_unique')
                    ->setType('unique')
            )
            ->create();

        $this->table('texts', ['id' => false])
            ->addColumn('title', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('description', 'text', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('users')
            ->addColumn('username', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => 256,
                'null' => true,
            ])
            ->addColumn('password', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
                'default' => null,
                'limit' => 256,
                'null' => true,
            ])
            ->addColumn('created', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->addColumn('updated', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->create();

        $this->table('articles')
            ->addForeignKey(
                $this->foreignKey('category_id')
                    ->setReferencedTable('categories')
                    ->setReferencedColumns('id')
                    ->setOnDelete('NO_ACTION')
                    ->setOnUpdate('NO_ACTION')
                    ->setName('articles_category_fk')
            )
            ->update();

        $this->table('orders')
            ->addForeignKey(
                $this->foreignKey([
                        'product_category',
                        'product_id',
                    ])
                    ->setReferencedTable('products')
                    ->setReferencedColumns([
                        'category_id',
                        'id',
                    ])
                    ->setOnDelete('CASCADE')
                    ->setOnUpdate('CASCADE')
                    ->setName('orders_product_fk')
            )
            ->update();

        $this->table('products')
            ->addForeignKey(
                $this->foreignKey('category_id')
                    ->setReferencedTable('categories')
                    ->setReferencedColumns('id')
                    ->setOnDelete('CASCADE')
                    ->setOnUpdate('CASCADE')
                    ->setName('products_category_fk')
            )
            ->update();
    }

    /**
     * Down Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/en/migrations.html#the-down-method
     * @return void
     */
    public function down(): void
    {
        $this->table('articles')
            ->dropForeignKey(
                'category_id'
            )->save();

        $this->table('orders')
            ->dropForeignKey(
                [
                    'product_category',
                    'product_id',
                ]
            )->save();

        $this->table('products')
            ->dropForeignKey(
                'category_id'
            )->save();

        $this->table('articles')->drop()->save();
        $this->table('categories')->drop()->save();
        $this->table('composite_pks')->drop()->save();
        $this->table('events')->drop()->save();
        $this->table('orders')->drop()->save();
        $this->table('parts')->drop()->save();
        $this->table('products')->drop()->save();
        $this->table('special_pks')->drop()->save();
        $this->table('special_tags')->drop()->save();
        $this->table('texts')->drop()->save();
        $this->table('users')->drop()->save();
    }
}
