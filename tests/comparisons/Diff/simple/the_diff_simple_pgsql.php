<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class TheDiffSimplePgsql extends AbstractMigration
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
        $this->table('users')
            ->addColumn('username', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('password', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->create();

        $this->table('articles')
            ->addColumn('user_id', 'integer', [
                'after' => 'name',
                'default' => null,
                'length' => 10,
                'null' => false,
            ])
            ->addIndex(
                [
                    'user_id',
                ],
                [
                    'name' => 'user_id',
                ]
            )
            ->update();

        $this->table('articles')
            ->addForeignKey(
                'user_id',
                'users',
                'id',
                [
                    'update' => 'RESTRICT',
                    'delete' => 'RESTRICT',
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
                'user_id'
            )->save();

        $this->table('articles')
            ->removeIndexByName('user_id')
            ->update();

        $this->table('articles')
            ->removeColumn('user_id')
            ->update();

        $this->table('users')->drop->save();
    }
}
