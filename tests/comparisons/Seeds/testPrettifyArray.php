<?php
use Migrations\AbstractSeed;

/**
 * Texts seed.
 */
class TextsSeed extends AbstractSeed
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
                'title' => 'Simple text',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat.',
            ],
            [
                'title' => 'Multi line',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat. 
Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum 
nullam, vivamus ut a sed, mollitia lectus. 
Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus 
duis vestibulum nunc mattis convallis.',
            ],
            [
                'title' => 'Multi line with quotes',
                'description' => 'Lorem ipsum dolor sit \'amet, aliquet feugiat. 
Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget \'sollicitudin\' venenatis cum',
            ],
            [
                'title' => 'Multi line with array keyword, bracket and =>',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat. 
Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, 
4 => 
pulvinar eget sollicitudin venenatis cum 
array ( 
Nulla vestibulum massa neque ut et, id hendrerit sit, 
arrray (\'foo\', \'bar\')
feugiat in taciti enim proin nibh, tempor dignissim, rhoncus 
)
duis vestibulum nunc mattis convallis.',
            ],
        ];

        $table = $this->table('texts');
        $table->insert($data)->save();
    }
}
