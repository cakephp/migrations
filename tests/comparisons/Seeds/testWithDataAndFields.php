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
                'title' => 'First Article',
                'body' => 'First Article Body',
            ],
            [
                'title' => 'Second Article',
                'body' => 'Second Article Body',
            ],
            [
                'title' => 'Third Article',
                'body' => 'Third Article Body',
            ],
        ];

        $table = $this->table('articles');
        $table->insert($data)->save();
    }
}
