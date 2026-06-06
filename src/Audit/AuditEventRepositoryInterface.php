<?php

declare(strict_types=1);

namespace NeneClear\Audit;

interface AuditEventRepositoryInterface
{
    public function record(AuditEvent $event): int;

    /**
     * Tenant-scoped. Filtered + sorted via {@see AuditEventFilter}.
     *
     * @return list<AuditEvent>
     */
    public function findByOrganization(int $organizationId, AuditEventFilter $filter, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId, AuditEventFilter $filter): int;
}
