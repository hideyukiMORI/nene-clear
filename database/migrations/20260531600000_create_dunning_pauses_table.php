<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDunningPausesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('dunning_pauses');
        $table
            ->addColumn('organization_id', 'biginteger', ['signed' => false])
            ->addColumn('invoice_id', 'biginteger', ['signed' => false])
            ->addColumn('paused_by', 'biginteger', ['signed' => false])
            ->addColumn('paused_at', 'datetime')
            ->addColumn('paused_reason', 'string', ['limit' => 512])
            ->addColumn('unpaused_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('unpaused_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['organization_id'])
            ->addIndex(['organization_id', 'invoice_id'])
            ->create();
    }
}
