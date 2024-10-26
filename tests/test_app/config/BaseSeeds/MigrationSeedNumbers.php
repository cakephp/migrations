<?php

use Migrations\BaseSeed;

/**
 * NumbersSeed seed.
 */
class MigrationSeedNumbers extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run(): void
    {
        $data = [
            [
                'number' => '5',
                'radix' => '10',
            ],
        ];

        // Call various methods on the seeder for runtime checks
        // and generate output to assert behavior with in an integration test.
        $this->table('numbers');
        $this->insert('numbers', $data);

        $this->call('AnotherNumbersSeed', ['source' => 'AltSeeds']);

        $io = $this->getIo();
        $query = $this->query('SELECT radix FROM numbers');
        $io->out('radix=' . $query->fetchColumn(0));

        $row = $this->fetchRow('SELECT 121 as row_val');
        $io->out('fetchRow=' . $row['row_val']);
        $io->out('hasTable=' . $this->hasTable('numbers'));

        $rows = $this->fetchAll('SELECT 121 as row_val');
        $io->out('fetchAll=' . $rows[0]['row_val']);
    }
}
