<?php

use Migrations\Db\Table\Column;
use Migrations\Db\Table\ForeignKey;
use Migrations\BaseMigration;

class AddColumnIndexFk extends BaseMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/migrations/5/en/migrations.html
     *
     * The following commands can be used in this method and Migrations will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('statuses')
            ->addColumn('user_id', Column::INTEGER, [
                'null' => true,
                'limit' => 20,
                'signed' => false,
            ])
            ->addIndex(['user_id'], [
                'name' => 'statuses_users_id',
                'unique' => false,
            ]);

        $table->addForeignKey('user_id', 'users', 'id', [
            'constraint' => 'statuses_users_id',
            'update' => ForeignKey::NO_ACTION,
            'delete' => ForeignKey::NO_ACTION,
        ]);

        $table->update();
    }
}
