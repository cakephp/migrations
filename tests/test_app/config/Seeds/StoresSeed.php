<?php

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Migrations\BaseSeed;

/**
 * StoresSeed seed.
 */
class StoresSeed extends BaseSeed
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
                'updated' => new Date(),
            ],
            [
                'name' => 'foo_with_date',
                'created' => new DateTime(),
                'updated' => new DateTime(),
            ],
        ];

        $table = $this->table('stores');
        $table->insert($data)->save();
    }
}
