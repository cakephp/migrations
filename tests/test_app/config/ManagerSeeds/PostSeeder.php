<?php

use Migrations\AbstractSeed;

class PostSeeder extends AbstractSeed
{
    public function run(): void
    {
        $data = [
            [
                'body' => 'foo',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'body' => 'bar',
                'created' => date('Y-m-d H:i:s'),
            ],
        ];

        $posts = $this->table('posts');
        $posts->insert($data)
              ->save();
    }

    public function getDependencies(): array
    {
        return [
            'UserSeeder',
            'GSeeder',
        ];
    }
}
