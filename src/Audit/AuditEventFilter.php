<?php

declare(strict_types=1);

namespace NeneClear\Audit;

/**
 * Server-side filter + sort for the audit-event list (Issue #130). Every field
 * is optional; null/empty means "no constraint". `sortColumn` is validated
 * against a whitelist in the repository.
 *
 * `occurredFrom` / `occurredTo` are inclusive `YYYY-MM-DD` bounds on
 * `DATE(occurred_at)`.
 */
final readonly class AuditEventFilter
{
    public function __construct(
        public ?string $action = null,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public ?int $actorId = null,
        public ?string $occurredFrom = null,
        public ?string $occurredTo = null,
        public string $sortColumn = 'occurred_at',
        public string $sortDirection = 'desc',
    ) {
    }
}
