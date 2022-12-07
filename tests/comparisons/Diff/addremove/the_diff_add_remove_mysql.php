<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class TheDiffAddRemoveMysql extends AbstractMigration
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
            ->removeColumn('excerpt')
            ->changeColumn('id', 'integer', [
                'default' => null,
                'length' => null,
                'limit' => null,
                'null' => false,
            ])
            ->update();

        $this->table('articles')
            ->addColumn('the_text', 'text', [
                'after' => 'title',
                'default' => null,
                'length' => null,
                'null' => false,
            ])
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
            ->addColumn('excerpt', 'text', [
                'after' => 'title',
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
            ->removeColumn('the_text')
            ->update();
    }
}
