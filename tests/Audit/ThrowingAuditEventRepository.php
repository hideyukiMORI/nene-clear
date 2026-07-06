<?php

declare(strict_types=1);

namespace NeneClear\Tests\Audit;

use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditEventRepositoryInterface;
use Nene2\Audit\AuditQuery;
use RuntimeException;

/**
 * Audit repository double whose {@see append()} always throws.
 *
 * Used to prove transactional atomicity: when the audit write fails, the
 * surrounding `transactional()` must roll back the business writes too, so no
 * state change is ever persisted without its audit event (Issue #122).
 */
final class ThrowingAuditEventRepository implements AuditEventRepositoryInterface
{
    public function append(AuditEvent $event): void
    {
        throw new RuntimeException('audit write failed (simulated)');
    }

    /**
     * @return list<AuditEvent>
     */
    public function query(AuditQuery $query, int $limit, int $offset): array
    {
        return [];
    }

    public function count(AuditQuery $query): int
    {
        return 0;
    }
}
