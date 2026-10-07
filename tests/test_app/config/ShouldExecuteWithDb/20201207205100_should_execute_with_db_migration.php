<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ShouldExecuteWithDbMigration extends BaseMigration
{
    public function change(): void
    {
        // info table
        $this->table('info')->create();
    }

    public function shouldExecute(): bool
    {
        // Query the database to decide eligibility. Before the fix the adapter
        // was not set at this point and getAdapter() threw 'Adapter not set.'.
        return !$this->getAdapter()->hasTable('info');
    }
}
