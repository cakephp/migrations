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
                'id' => '1',
                'author_id' => '1',
                'title' => 'First Article',
                'body' => 'First Article Body',
                'published' => 'Y',
            ],
            [
                'id' => '2',
                'author_id' => '3',
                'title' => 'Second Article',
                'body' => 'Second Article Body',
                'published' => 'Y',
            ],
        ];

        $table = $this->table('articles');
        $table->insert($data)->save();
    }
}
