<?php
use Phinx\Migration\AbstractMigration;

class NotEmptySnapshot extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('special_tags');
        $table
            ->addColumn('id', 'integer', [
                'limit' => 'null',
                'null' => 'false',
                'default' => 'null'
            ])
            ->addColumn('article_id', 'integer', [
                'limit' => 'null',
                'null' => 'false',
                'default' => 'null'
            ])
            ->addColumn('tag_id', 'integer', [
                'limit' => 'null',
                'null' => 'false',
                'default' => 'null'
            ])
            ->addColumn('highlighted', 'boolean', [
                'limit' => 'null',
                'null' => '1',
                'default' => 'NULL'
            ])
            ->addColumn('highlighted_time', 'timestamp', [
                'limit' => 'null',
                'null' => '1',
                'default' => 'NULL'
            ])
            ->addColumn('author_id', 'integer', [
                'limit' => 'null',
                'null' => '1',
                'default' => 'NULL'
            ])
            ->update();
        $table = $this->table('users');
        $table
            ->addColumn('id', 'integer', [
                'limit' => 'null',
                'null' => 'false',
                'default' => 'null'
            ])
            ->addColumn('username', 'string', [
                'limit' => 'null',
                'null' => '1',
                'default' => 'NULL'
            ])
            ->addColumn('password', 'string', [
                'limit' => 'null',
                'null' => '1',
                'default' => 'NULL'
            ])
            ->addColumn('created', 'timestamp', [
                'limit' => 'null',
                'null' => '1',
                'default' => 'NULL'
            ])
            ->addColumn('updated', 'timestamp', [
                'limit' => 'null',
                'null' => '1',
                'default' => 'NULL'
            ])
            ->update();
    }
}
