<?php

use Migrations\BaseSeed;

/**
 * DatabaseSeed seed.
 */
class DatabaseSeed extends BaseSeed
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
        $this->call('NumbersCallSeed');
        $this->call('LettersSeed');
        $this->call('TestBlog.PluginLettersSeed');
    }
}
