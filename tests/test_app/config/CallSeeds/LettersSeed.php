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
     * https://book.cakephp.org/phinx/0/en/seeding.html
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
