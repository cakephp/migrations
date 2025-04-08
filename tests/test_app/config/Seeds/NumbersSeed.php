<?php

use Migrations\AbstractSeed;

/**
 * NumbersSeed seed.
 */
class NumbersSeed extends AbstractSeed
{
    /**
     * Run Method.
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
