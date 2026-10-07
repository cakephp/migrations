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
     * https://book.cakephp.org/migrations/5/guides/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'anonymous_store',
            ],
            [
                'name' => 'other_store',
            ],
        ];

        $table = $this->table('stores');
        $table->insert($data)->save();
    }
};
