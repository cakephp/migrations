<?php

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Migrations\AbstractSeed;

/**
 * NumbersSeed seed.
 */
class StoresSeed extends AbstractSeed
{
    /**
     * Run Method.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'foo',
                'created' => new Date(),
                'modified' => new Date(),
            ],
            [
                'name' => 'foo_with_date',
                'created' => new DateTime(),
                'modified' => new DateTime(),
            ],
        ];

        $table = $this->table('stores');
        $table->insert($data)->save();
    }
}
