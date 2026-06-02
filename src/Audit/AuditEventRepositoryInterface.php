<?php

declare(strict_types=1);

namespace NeneClear\Audit;

interface AuditEventRepositoryInterface
{
    public function record(AuditEvent $event): int;

    /**
     * Tenant-scoped, newest first. `$eventType` filters to a single
     * `event_type` when non-null (see terminology §2 for the closed set).
     *
     * @return list<AuditEvent>
     */
    public function findByOrganization(int $organizationId, ?string $eventType, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId, ?string $eventType): int;
}
