<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBankAccountsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('bank_accounts')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('bank_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('bank_branch', 'string', ['limit' => 255, 'null' => false, 'default' => ''])
            ->addColumn('account_type', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('account_number', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('csv_encoding', 'string', ['limit' => 32, 'null' => false, 'default' => 'utf8'])
            ->addColumn('csv_date_format', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('csv_date_column', 'integer', ['null' => false])
            ->addColumn('csv_amount_column', 'integer', ['null' => false])
            ->addColumn('csv_counterparty_column', 'integer', ['null' => false])
            ->addColumn('csv_header_rows', 'integer', ['null' => false, 'default' => 1])
            ->addTimestamps()
            ->addIndex(['organization_id'])
            ->create();
    }
}
