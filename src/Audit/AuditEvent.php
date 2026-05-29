<?php

declare(strict_types=1);

namespace NeneClear\Audit;

/**
 * Immutable audit record (compliance §6). Append-only; never updated or deleted.
 */
final readonly class AuditEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $organizationId,
        public string $eventType,
        public int $actorUserId,
        public string $occurredAt,
        public array $payload,
        public ?int $id = null,
    ) {
    }
}
