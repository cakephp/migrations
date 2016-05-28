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
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        $this->call('NumbersCallSeed');
        $this->call('LettersSeed');
    }
}
