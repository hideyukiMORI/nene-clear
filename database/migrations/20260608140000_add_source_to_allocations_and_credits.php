<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Makes reconciliation allocations and client credits source-aware (ADR 0014):
 * an allocation/credit can target an upstream invoice (Invoice is SSOR, payment
 * written back) or a manually-entered receivable (Clear is SSOR, no write-back).
 * Existing rows default to `invoice_upstream` — behaviour is unchanged.
 */
final class AddSourceToAllocationsAndCredits extends AbstractMigration
{
    public function change(): void
    {
        $this->table('reconciliation_allocations')
            ->addColumn('source', 'string', ['limit' => 32, 'default' => 'invoice_upstream'])
            ->addColumn('manual_receivable_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addIndex(['manual_receivable_id'])
            ->update();

        // For a manual overpayment there is no upstream client_id; the payer is
        // snapshotted as client_name instead. invoice/upstream credits keep client_id.
        $this->table('client_credits')
            ->changeColumn('client_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('source', 'string', ['limit' => 32, 'default' => 'invoice_upstream'])
            ->addColumn('manual_receivable_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('client_name', 'string', ['limit' => 255, 'null' => true])
            ->update();
    }
}
