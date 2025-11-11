<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class TheDiffDecimalChangeMysql extends BaseMigration
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
            ->changeColumn('id', 'integer', [
                'default' => null,
                'length' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->changeColumn('amount', 'decimal', [
                'default' => null,
                'null' => false,
                'precision' => 5,
                'scale' => 2,
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

        $this->table('test_decimal_types')
            ->changeColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'length' => 11,
                'null' => false,
            ])
            ->changeColumn('amount', 'decimal', [
                'default' => null,
                'null' => false,
                'precision' => 10,
                'scale' => 2,
            ])
            ->update();
    }
}
