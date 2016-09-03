<?php
use Migrations\AbstractSeed;

/**
 * Articles seed.
 */
class ArticlesSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'title' => 'Lorem ipsum dolor sit amet',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat. 
Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget \'sollicitudin\' venenatis cum 
nullam, vivamus ut a sed, mollitia lectus. 
Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus 
duis vestibulum nunc mattis convallis.',
            ],
            [
                'title' => 'Lorem ipsum dolor sit amet',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat.',
            ],
        ];

        $table = $this->table('events');
        $table->insert($data)->save();
    }
}
