<?php

use Migrations\BaseSeed;

/**
 * NumbersSeed seed.
 */
class MigrationSeedNumbers extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run(): void
    {
        $data = [
            [
                'number' => '5',
                'radix' => '10',
            ],
        ];

        $table = $this->table('numbers');
        $table->insert($data)->save();
    }
}
