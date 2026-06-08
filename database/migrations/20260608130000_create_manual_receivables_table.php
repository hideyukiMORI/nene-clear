<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Manually-entered receivables (ADR 0014). A Clear-owned receivable that exists
 * only in Clear (no NeNe Invoice upstream), so Clear is its system of record:
 * it stores the figures and computes `outstanding_cents` / `status` itself.
 * This is a reconciliation reference (a receivable stub), NOT an invoice — Clear
 * issues nothing and computes no tax (scope-contract X1).
 */
final class CreateManualReceivablesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('manual_receivables')
            ->addColumn('organization_id', 'biginteger', ['signed' => false])
            ->addColumn('reference_number', 'string', ['limit' => 64])
            ->addColumn('client_name', 'string', ['limit' => 255])
            ->addColumn('recipient_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('total_cents', 'biginteger')
            ->addColumn('outstanding_cents', 'biginteger')
            ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'JPY'])
            ->addColumn('issued_at', 'date', ['null' => true])
            ->addColumn('due_at', 'date', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'open'])
            ->addColumn('created_by', 'biginteger', ['signed' => false])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addColumn('is_deleted', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex(['organization_id'])
            ->addIndex(['organization_id', 'status'])
            ->create();
    }
}
