<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateClientCreditsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('client_credits');
        $table
            ->addColumn('organization_id', 'biginteger', ['signed' => false])
            ->addColumn('client_id', 'biginteger', ['signed' => false])
            ->addColumn('amount_cents', 'biginteger')
            ->addColumn('remaining_cents', 'biginteger')
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'open'])
            ->addColumn('source_bank_transaction_id', 'biginteger', ['signed' => false])
            ->addColumn('reconciliation_id', 'biginteger', ['signed' => false])
            ->addColumn('created_by', 'biginteger', ['signed' => false])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['organization_id'])
            ->addIndex(['client_id'])
            ->addIndex(['reconciliation_id'])
            ->create();
    }
}
