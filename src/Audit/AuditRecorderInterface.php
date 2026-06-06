<?php

declare(strict_types=1);

namespace NeneClear\Audit;

/**
 * Records one mutating operation in the audit trail (ADR 0008 in nene-invoice,
 * adopted here). Use cases call this instead of building {@see AuditEvent}s by
 * hand, so who/what/before/after recording stays uniform across domains.
 *
 * The recorder is built from the active transaction's executor, so the audit
 * write commits or rolls back together with the business writes (Issue #122).
 */
interface AuditRecorderInterface
{
    /**
     * @param string                $entityType subject record type (terminology §audit entity types)
     * @param ?int                  $entityId   subject record id (null when there is no single id)
     * @param array<string, mixed>  $payload    before/after snapshot (no secrets)
     */
    public function record(
        int $organizationId,
        int $actorUserId,
        string $occurredAt,
        string $eventType,
        string $entityType,
        ?int $entityId,
        array $payload,
    ): int;
}
