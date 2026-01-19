<?php

use Migrations\BaseSeed;

/**
 * NumbersCallSeed seed.
 */
class NumbersCallSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/migrations/5/en/seeding.html
     */
    public function run(): void
    {
        $data = [
            [
                'number' => '10',
                'radix' => '10',
            ],
        ];

        $table = $this->table('numbers');
        $table->insert($data)->save();
    }
}
