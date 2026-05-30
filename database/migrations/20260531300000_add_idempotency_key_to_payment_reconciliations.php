<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddIdempotencyKeyToPaymentReconciliations extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payment_reconciliations')
            ->addColumn('idempotency_key', 'string', [
                'limit' => 128,
                'null' => true,
                'default' => null,
                'after' => 'organization_id',
            ])
            ->addIndex(['organization_id', 'idempotency_key'], ['unique' => true, 'name' => 'uq_recon_org_idempotency_key'])
            ->update();
    }
}
