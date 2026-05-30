<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateClearSettingsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('clear_settings', ['id' => false, 'primary_key' => ['organization_id']]);
        $table
            ->addColumn('organization_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('upstream_base_url', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('upstream_token_ref', 'string', ['limit' => 128, 'default' => ''])
            ->addColumn('dunning_min_interval_days', 'integer', ['default' => 7])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => null])
            ->create();
    }
}
