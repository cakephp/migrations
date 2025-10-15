<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class TheDiffDecimalChangeMysql extends BaseMigration
{
    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-up-method
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('products')
            ->changeColumn('price', 'decimal', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'precision' => 10,
                'scale' => 2,
            ])
            ->update();
    }

    /**
     * Down Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-down-method
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('products')
            ->changeColumn('price', 'decimal', [
                'default' => null,
                'null' => false,
                'precision' => 8,
                'scale' => 2,
            ])
            ->update();
    }
}
