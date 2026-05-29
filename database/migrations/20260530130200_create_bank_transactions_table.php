<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBankTransactionsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('bank_transactions')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('bank_import_batch_id', 'integer', ['null' => false])
            ->addColumn('bank_account_id', 'integer', ['null' => false])
            ->addColumn('value_date', 'date', ['null' => false])
            ->addColumn('amount_cents', 'biginteger', ['null' => false])
            ->addColumn('counterparty_text', 'string', ['limit' => 255, 'null' => false, 'default' => ''])
            ->addColumn('line_key', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 32, 'null' => false, 'default' => 'unmatched'])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addIndex(['organization_id', 'status'])
            ->addIndex(['bank_import_batch_id'])
            ->addIndex(['value_date'])
            ->addIndex(['organization_id', 'counterparty_text'])
            ->create();
    }
}
