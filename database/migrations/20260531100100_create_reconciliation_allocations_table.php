<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateReconciliationAllocationsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('reconciliation_allocations');
        $table
            ->addColumn('organization_id', 'biginteger', ['signed' => false])
            ->addColumn('payment_reconciliation_id', 'biginteger', ['signed' => false])
            ->addColumn('invoice_id', 'biginteger', ['signed' => false])
            ->addColumn('amount_cents', 'biginteger')
            ->addColumn('payment_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('external_reference', 'string', ['limit' => 64, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['organization_id'])
            ->addIndex(['payment_reconciliation_id'])
            ->create();
    }
}
