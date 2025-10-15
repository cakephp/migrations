<?php
declare(strict_types=1);

use Migrations\BaseSeed;

return new class extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/migrations/5/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                'id' => '1',
                'name' => 'Product 1',
            ],
            [
                'id' => '2',
                'name' => 'Product 2',
            ],
        ];

        $table = $this->table('products');
        $table->insert($data)->save();
    }
};
