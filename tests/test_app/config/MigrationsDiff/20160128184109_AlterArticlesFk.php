<?php
use Migrations\AbstractMigration;

class AlterArticlesFk extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('articles');
        $table
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ]);
        $table->update();

        $table->addForeignKey(
            'user_id',
            'users',
            'id',
            [
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ]
        );

        $table->update();
    }
}
