<?php

declare(strict_types=1);

namespace NeneClear\Audit;

/**
 * Immutable audit record (compliance §6). Append-only; never updated or deleted.
 *
 * `entityType` / `entityId` identify the record the operation changed (ADR 0008
 * in nene-invoice; adopted here keeping Clear's `audit_events` naming), so a
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
