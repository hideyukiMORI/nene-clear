<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePaymentReconciliationsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('payment_reconciliations');
        $table
            ->addColumn('organization_id', 'biginteger', ['signed' => false])
            ->addColumn('bank_transaction_id', 'biginteger', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'confirmed'])
            ->addColumn('reason_code', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('confirmed_by', 'biginteger', ['signed' => false])
            ->addColumn('confirmed_at', 'datetime')
            ->addColumn('reversed_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('reversal_reason', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['organization_id'])
            ->addIndex(['bank_transaction_id'])
            ->create();
    }
}
