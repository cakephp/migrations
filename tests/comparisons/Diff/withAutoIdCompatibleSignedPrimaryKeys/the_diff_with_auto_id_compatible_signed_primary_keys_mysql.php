<?php
declare(strict_types=1);

use Migrations\BaseMigration;
use Migrations\DirectionalMigrationInterface;

class TheDiffWithAutoIdCompatibleSignedPrimaryKeysMysql extends BaseMigration implements DirectionalMigrationInterface
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
        $this->table('comments')
            ->create();

        $this->table('articles')->drop()->save();
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
        $this->table('articles')
            ->create();

        $this->table('comments')->drop()->save();
    }
}
