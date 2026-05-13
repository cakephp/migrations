<?php
declare(strict_types=1);

use Migrations\BaseMigration;
use Migrations\ReversibleMigrationInterface;

class CreateArticles extends BaseMigration implements ReversibleMigrationInterface
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('articles');
        $table->addColumn('title', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('author_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        $table->addForeignKey(
            $this->foreignKey('author_id')
                ->setReferencedTable('authors')
                ->setReferencedColumns('id')
                ->setDelete('CASCADE')
                ->setUpdate('CASCADE')
                ->setName('fk_author_id')
        );
        $table->create();
    }
}
