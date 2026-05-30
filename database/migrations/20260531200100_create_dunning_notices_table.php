<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDunningNoticesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('dunning_notices');
        $table
            ->addColumn('organization_id', 'biginteger', ['signed' => false])
            ->addColumn('invoice_id', 'biginteger', ['signed' => false])
            ->addColumn('invoice_number', 'string', ['limit' => 64])
            ->addColumn('client_id', 'biginteger', ['signed' => false])
            ->addColumn('recipient_email', 'string', ['limit' => 255])
            ->addColumn('outstanding_cents', 'biginteger')
            ->addColumn('due_at', 'date')
            ->addColumn('channel', 'string', ['limit' => 32, 'default' => 'log'])
            ->addColumn('sent_by', 'biginteger', ['signed' => false])
            ->addColumn('sent_at', 'datetime')
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['organization_id'])
            ->addIndex(['organization_id', 'invoice_id'])
            ->create();
    }
}
