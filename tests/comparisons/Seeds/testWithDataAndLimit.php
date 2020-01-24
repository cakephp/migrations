<?php
use Migrations\AbstractSeed;

/**
 * Events seed.
 */
class EventsSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'id' => '1',
                'title' => 'Lorem ipsum dolor sit amet',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat.',
                'published' => 'Y',
            ],
            [
                'id' => '2',
                'title' => 'Second event',
                'description' => 'Second event description.',
                'published' => 'Y',
            ],
        ];

        $table = $this->table('events');
        $table->insert($data)->save();
    }
}
