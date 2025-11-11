<?php
declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * IdempotentTestSeed seed.
 */
class IdempotentTestSeed extends BaseSeed
{
    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(): void
    {
        $this->table('numbers')
            ->insert([
                'number' => '99',
                'radix' => '10',
            ])
            ->save();
    }
}
