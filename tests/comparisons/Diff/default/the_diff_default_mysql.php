<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class TheDiffDefaultMysql extends BaseMigration
{
    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/en/migrations.html#the-up-method
     *
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
            ])
            ->update();

        $this->table('tags')
            ->changeColumn('id', 'integer', [
                'default' => null,
                'length' => null,
                'limit' => null,
                'null' => false,
            ])
            ->update();

        $this->table('users')
            ->changeColumn('id', 'integer', [
                'default' => null,
                'length' => null,
                'limit' => null,
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
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addIndex(
                $this->index('user_id')
                    ->setName('categories_ibfk_1')
            )
            ->addIndex(
                $this->index('name')
                    ->setName('name')
            )
            ->create();

        $this->table('categories')
            ->addForeignKey(
                $this->foreignKey('user_id')
                    ->setReferencedTable('users')
                    ->setReferencedColumns('id')
                    ->setOnDelete('RESTRICT')
                    ->setOnUpdate('RESTRICT')
                    ->setName('categories_ibfk_1')
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
            ])
            ->addIndex(
                $this->index('slug')
                    ->setName('UNIQUE_SLUG')
            )
            ->addIndex(
                $this->index('category_id')
                    ->setName('category_id')
            )
            ->addIndex(
                $this->index('name')
                    ->setName('rating_index')
            )
            ->update();
    }

    /**
     * Down Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/en/migrations.html#the-down-method
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('categories')
            ->dropForeignKey(
                'user_id'
            )->save();

        $this->table('articles')
            ->removeIndexByName('UNIQUE_SLUG')
            ->removeIndexByName('category_id')
            ->removeIndexByName('rating_index')
            ->update();

        $this->table('articles')
            ->addColumn('content', 'text', [
                'after' => 'rating',
                'collation' => 'utf8_general_ci',
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
                'collation' => 'utf8_general_ci',
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
                'collation' => 'utf8_general_ci',
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
                $this->index('slug')
                    ->setName('UNIQUE_SLUG')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('rating')
                    ->setName('rating_index')
            )
            ->addIndex(
                $this->index('name')
                    ->setName('BY_NAME')
            )
            ->update();

        $this->table('tags')
            ->changeColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'length' => 11,
                'null' => false,
            ])
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
                $this->foreignKey('user_id')
                    ->setReferencedTable('users')
                    ->setReferencedColumns('id')
                    ->setOnDelete('CASCADE')
                    ->setOnUpdate('CASCADE')
                    ->setName('articles_ibfk_1')
            )
            ->update();

        $this->table('categories')->drop()->save();
    }
}
