<?php

declare(strict_types=1);

namespace NeneClear\Audit;

interface AuditEventRepositoryInterface
{
    public function record(AuditEvent $event): int;

    /**
     * Tenant-scoped, newest first. Each non-null filter narrows the result:
     * `$eventType` to a single `event_type` (terminology §2), `$entityType` /
     * `$entityId` to a single subject record.
     *
     * @return list<AuditEvent>
     */
    public function findByOrganization(
        int $organizationId,
        ?string $eventType,
        ?string $entityType,
        ?int $entityId,
        int $limit,
        int $offset,
    ): array;

    public function countByOrganization(
        int $organizationId,
        ?string $eventType,
        ?string $entityType,
        ?int $entityId,
    ): int;
}
