<?php
use Migrations\AbstractSeed;

/**
 * NumbersSeed seed.
 */
class DatabaseSeed extends AbstractSeed
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
        $this->call('NumbersCallSeed');
        $this->call('LettersSeed');
        $this->call('TestBlog.PluginLettersSeed');
    }
}
