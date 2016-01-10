<?php
use Phinx\Seed\AbstractSeed;

/**
 * NumbersSeed seed.
 */
class NumbersAltSeed extends AbstractSeed
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
                'number' => '5',
                'radix' => '10'
            ]
        ];

        $table = $this->table('numbers');
        $table->insert($data)->save();
    }
}
