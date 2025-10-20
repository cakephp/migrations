<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class TheDiffDecimalColumnMysql extends BaseMigration
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
        $this->table('products')
            ->changeColumn('price', 'decimal', [
                'default' => null,
                'null' => false,
                'precision' => 6,
                'scale' => 2,
                'type' => 'decimal',
            ])
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
        $this->table('products')
            ->changeColumn('price', 'decimal', [
                'default' => null,
                'null' => false,
                'precision' => 4,
                'scale' => 2,
                'type' => 'decimal',
            ])
            ->update();
    }
}
