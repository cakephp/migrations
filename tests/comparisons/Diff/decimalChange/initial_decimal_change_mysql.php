<?php
declare(strict_types=1);

use Migrations\BaseMigration;
use Migrations\DirectionalMigrationInterface;

class InitialDecimalChangeMysql extends BaseMigration implements DirectionalMigrationInterface
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
        $this->table('test_decimal_types')
            ->addColumn('amount', 'decimal', [
                'default' => null,
                'null' => false,
                'precision' => 5,
                'scale' => 2,
            ])
            ->create();
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
        $this->table('test_decimal_types')->drop()->save();
    }
}
