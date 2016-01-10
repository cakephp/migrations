<?php
use Phinx\Seed\AbstractSeed;

/**
 * NumbersSeed seed.
 */
class NumbersSeed extends AbstractSeed
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
                'id' => '1',
                'number' => '10',
                'radix' => '10'
            ]
        ];

        $table = $this->table('numbers');
        $table->insert($data)->save();
    }
}
