<?php

use Migrations\BaseSeed;

/**
 * NumbersSeed seed.
 */
class PluginLettersSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/migrations/5/guides/seeding.html
     */
    public function run(): void
    {
        $data = [
            [
                'letter' => 'c',
            ],
            [
                'letter' => 'd',
            ],
        ];

        $table = $this->table('letters');
        $table->insert($data)->save();

        $this->call('TestBlog.PluginSubLettersSeed');
    }
}
