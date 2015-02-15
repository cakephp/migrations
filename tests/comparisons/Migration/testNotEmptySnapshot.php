<?php
use Phinx\Migration\AbstractMigration;

class NotEmptySnapshot extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('special_tags');
        $table
            ->addColumn('article_id', 'integer', [
                'limit' => 11,
                'null' => false,
                'default' => null,
            ])
            ->addColumn('author_id', 'integer', [
                'limit' => 11,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('tag_id', 'integer', [
                'limit' => 11,
                'null' => false,
                'default' => null,
            ])
            ->addColumn('highlighted', 'boolean', [
                'limit' => null,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('highlighted_time', 'timestamp', [
                'limit' => null,
                'null' => true,
                'default' => null,
            ])
            ->create();
        $table = $this->table('users');
        $table
            ->addColumn('username', 'string', [
                'limit' => 256,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('password', 'string', [
                'limit' => 256,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('created', 'timestamp', [
                'limit' => null,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('updated', 'timestamp', [
                'limit' => null,
                'null' => true,
                'default' => null,
            ])
            ->create();
    }
}
