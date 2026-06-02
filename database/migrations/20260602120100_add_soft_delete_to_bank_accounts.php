<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSoftDeleteToBankAccounts extends AbstractMigration
{
    public function change(): void
    {
        // Bank accounts are referenced by imported transactions and batches, so
        // replacing an organization's CSV profiles soft-deletes the old rows
        // instead of hard-deleting them (no orphaned references, full history).
        $this->table('bank_accounts')
            ->addColumn('is_deleted', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->update();
    }
}
