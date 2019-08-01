<?php

use Migrations\AbstractSeed;

/**
 * NumbersSeed seed.
 */
class LettersSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        $data = [
            [
                'letter' => 'a',
            ],
            [
                'letter' => 'b',
            ],
        ];

        $table = $this->table('letters');
        $table->insert($data)->save();
    }
}
