<?php

declare(strict_types=1);

namespace NeneClear\Audit;

/**
 * Read-side view of one audit record (compliance §6).
 *
 * Writes now go through the framework recorder (`Nene2\Audit\AuditEvent`, ADR
 * 0014); this product value object is the shape the read layer hydrates from
 * `audit_events` and hands to {@see AuditEventResponse}. `payload` is the
 * normalized flat payload (see {@see PdoAuditReadRepository}).
 *
 * `entityType` / `entityId` identify the record the operation changed, so a
 * single record's history is queryable. `entityId` is null when the subject has
 * no single id (e.g. a failed login).
 */
final readonly class AuditEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $organizationId,
        public string $eventType,
        public string $entityType,
        public ?int $entityId,
        public int $actorUserId,
        public string $occurredAt,
        public array $payload,
        public ?int $id = null,
    ) {
    }
}
