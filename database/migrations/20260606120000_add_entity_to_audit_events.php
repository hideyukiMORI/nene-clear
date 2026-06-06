<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds structured subject identification to the audit trail (Issue #124):
 * `entity_type` (the kind of record changed) and `entity_id` (which one), so a
 * single record's history is queryable. Mirrors nene-invoice ADR 0008 while
 * keeping Clear's `audit_events` naming.
 */
final class AddEntityToAuditEvents extends AbstractMigration
{
    public function change(): void
    {
        $this->table('audit_events')
            ->addColumn('entity_type', 'string', ['limit' => 64, 'null' => false, 'default' => '', 'after' => 'event_type'])
            ->addColumn('entity_id', 'integer', ['null' => true, 'default' => null, 'after' => 'entity_type'])
            ->addIndex(['entity_type', 'entity_id'])
            ->update();
    }
}
