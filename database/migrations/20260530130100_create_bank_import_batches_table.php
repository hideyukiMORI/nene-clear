<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBankImportBatchesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('bank_import_batches')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('bank_account_id', 'integer', ['null' => false])
            ->addColumn('file_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('source_filename', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('row_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('status', 'string', ['limit' => 32, 'null' => false, 'default' => 'imported'])
            ->addColumn('imported_by', 'integer', ['null' => false])
            ->addColumn('imported_at', 'datetime', ['null' => false])
            ->addColumn('reversed_at', 'datetime', ['null' => true])
            ->addColumn('reversal_reason', 'string', ['limit' => 255, 'null' => true])
            ->addTimestamps()
            ->addIndex(['organization_id', 'file_hash'], ['unique' => true])
            ->addIndex(['organization_id'])
            ->create();
    }
}
