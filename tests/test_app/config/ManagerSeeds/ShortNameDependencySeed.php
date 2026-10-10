<?php

declare(strict_types=1);

use Migrations\BaseSeed;

class ShortNameDependencySeed extends BaseSeed
{
    public function run(): void
    {
        $data = [
            [
                'body' => 'dependency_test',
                'created' => date('Y-m-d H:i:s'),
            ],
        ];

        $posts = $this->table('posts');
        $posts->insert($data)->save();
    }

    public function getDependencies(): array
    {
        return [
            'User',  // Short name without 'Seeder' suffix
            'G',     // Short name without 'Seeder' suffix
        ];
    }
}
