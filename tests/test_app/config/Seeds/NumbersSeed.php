<?php

use Migrations\BaseSeed;

/**
 * NumbersSeed seed.
 */
class NumbersSeed extends BaseSeed
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
