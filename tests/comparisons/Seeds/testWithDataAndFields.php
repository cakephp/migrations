<?php
declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * Events seed.
 */
class EventsSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/migrations/4/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                'title' => 'Lorem ipsum dolor sit amet',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat.',
            ],
            [
                'title' => 'Second event',
                'description' => 'Second event description.',
            ],
            [
                'title' => 'Lorem ipsum dolor sit amet',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat.
Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget \'sollicitudin\' venenatis cum
nullam, vivamus ut a sed, mollitia lectus.
Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus
duis vestibulum nunc mattis convallis.',
            ],
        ];

        $table = $this->table('events');
        $table->insert($data)->save();
    }
}
