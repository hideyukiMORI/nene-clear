<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAuditEventsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('audit_events')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('event_type', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('actor_user_id', 'integer', ['null' => false])
            ->addColumn('occurred_at', 'datetime', ['null' => false])
            ->addColumn('payload_json', 'text', ['null' => false])
            ->addIndex(['organization_id', 'event_type'])
            ->create();
    }
}
