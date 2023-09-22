<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class TheDiffWithAutoIdIncompatibleSignedPrimaryKeysMysql extends AbstractMigration
{
    public bool $autoId = false;

    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-up-method
     * @return void
     */
    public function up(): void
    {
        $this->table('comments')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => true,
            ])
            ->addPrimaryKey(['id'])
            ->create();

        $this->table('articles')->drop()->save();
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
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => true,
            ])
            ->addPrimaryKey(['id'])
            ->create();

        $this->table('comments')->drop()->save();
    }
}
