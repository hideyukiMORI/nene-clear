<?php

declare(strict_types=1);

namespace NeneClear\Audit;

/**
 * Read-side view of one audit record (compliance §6).
 *
 * Writes go through the framework recorder (`Nene2\Audit\AuditEvent`, ADR
 * 0014); this product value object is the shape the read layer hydrates from
 * the canonical `audit_events` columns (stage 2, Issue #258) and hands to
 * {@see AuditEventResponse}: `before` / `after` are the sanitized snapshots,
 * `metadata` carries the event's extra context keys (e.g. `invoice_id` on
 * dunning pauses) — each null when the event recorded none.
 *
 * `entityType` / `entityId` identify the record the operation changed, so a
 * single record's history is queryable. `entityId` is null when the subject has
 * no single id (e.g. a failed login).
 */
final readonly class AuditEvent
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public int $organizationId,
        public string $action,
        public string $entityType,
        public ?int $entityId,
        public int $actorId,
        public string $occurredAt,
        public ?array $before,
        public ?array $after,
        public ?array $metadata,
        public ?int $id = null,
    ) {
    }
}
