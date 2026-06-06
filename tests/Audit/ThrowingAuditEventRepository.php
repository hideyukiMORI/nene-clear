<?php

declare(strict_types=1);

namespace NeneClear\Tests\Audit;

use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventFilter;
use NeneClear\Audit\AuditEventRepositoryInterface;
use RuntimeException;

/**
 * Audit repository double whose {@see record()} always throws.
 *
 * Used to prove transactional atomicity: when the audit write fails, the
 * surrounding `transactional()` must roll back the business writes too, so no
 * state change is ever persisted without its audit event (Issue #122).
 */
final class ThrowingAuditEventRepository implements AuditEventRepositoryInterface
{
    public function record(AuditEvent $event): int
    {
        throw new RuntimeException('audit write failed (simulated)');
    }

    public function findByOrganization(int $organizationId, AuditEventFilter $filter, int $limit, int $offset): array
    {
        return [];
    }

    public function countByOrganization(int $organizationId, AuditEventFilter $filter): int
    {
        return 0;
    }
}
