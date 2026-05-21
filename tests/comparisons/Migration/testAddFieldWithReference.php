<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddCategoryIdToProducts extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/guides/writing-migrations/migration-methods.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('products');
        $table->addColumn('category_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        $table->addForeignKey(
            $this->foreignKey('category_id')
                ->setReferencedTable('categories')
                ->setReferencedColumns('id')
                ->setDelete('CASCADE')
                ->setUpdate('CASCADE')
                ->setName('fk_category_id')
        );
        $table->update();
    }
}
