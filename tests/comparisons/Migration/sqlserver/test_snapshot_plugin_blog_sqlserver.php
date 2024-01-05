<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class TestSnapshotPluginBlogSqlserver extends AbstractMigration
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
            ->addColumn('title', 'string', [
                'collation' => 'SQL_Latin1_General_CP1_CI_AS',
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
            ->addColumn('created', 'datetimefractional', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->addColumn('modified', 'datetimefractional', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->addIndex(
                [
                    'title',
                ],
                [
                    'name' => 'articles_title_idx',
                ]
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
            ->addColumn('created', 'datetimefractional', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->addColumn('modified', 'datetimefractional', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'precision' => 7,
                'scale' => 7,
            ])
            ->addIndex(
                [
                    'slug',
                ],
                [
                    'name' => 'categories_slug_unique',
                    'unique' => true,
                ]
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

        $this->table('articles')
            ->addForeignKey(
                'category_id',
                'categories',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'NO_ACTION',
                    'constraint' => 'articles_category_fk'
                ]
            )
            ->update();
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
        $this->table('articles')
            ->dropForeignKey(
                'category_id'
            )->save();

        $this->table('articles')->drop()->save();
        $this->table('categories')->drop()->save();
        $this->table('parts')->drop()->save();
    }
}
