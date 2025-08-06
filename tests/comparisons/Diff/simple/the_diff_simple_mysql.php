<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class TheDiffSimpleMysql extends BaseMigration
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
            ->changeColumn('id', 'integer', [
                'default' => null,
                'length' => null,
                'limit' => null,
                'null' => false,
                'signed' => true,
            ])
            ->changeColumn('rating', 'integer', [
                'default' => null,
                'length' => null,
                'limit' => null,
                'null' => false,
            ])
            ->update();
        $this->table('tags')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->create();

        $this->table('users')
            ->addColumn('username', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('password', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->create();

        $this->table('articles')
            ->addColumn('user_id', 'integer', [
                'after' => 'name',
                'default' => null,
                'length' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addIndex(
                $this->index('user_id')
                    ->setName('user_id')
            )
            ->update();

        $this->table('articles')
            ->addForeignKey(
                $this->foreignKey('user_id')
                    ->setReferencedTable('users')
                    ->setReferencedColumns('id')
                    ->setOnDelete('RESTRICT')
                    ->setOnUpdate('RESTRICT')
                    ->setName('articles_ibfk_1')
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
                'user_id'
            )->save();

        $this->table('articles')
            ->removeIndexByName('user_id')
            ->update();

        $this->table('articles')
            ->changeColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'length' => 11,
                'null' => false,
            ])
            ->changeColumn('rating', 'integer', [
                'default' => null,
                'length' => 11,
                'null' => false,
            ])
            ->removeColumn('user_id')
            ->update();

        $this->table('tags')->drop()->save();
        $this->table('users')->drop()->save();
    }
}
