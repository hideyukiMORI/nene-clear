<?php

declare(strict_types=1);

namespace NeneClear\Tests\Audit;

use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditEventRepositoryInterface;
use Nene2\Audit\AuditQuery;

/**
 * In-memory {@see AuditEventRepositoryInterface} double. Captures appended
 * framework {@see AuditEvent}s so unit tests can assert on `action` / `before` /
 * `after` / `metadata` without a database.
 */
final class InMemoryAuditEventRepository implements AuditEventRepositoryInterface
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function append(AuditEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<AuditEvent>
     */
    public function query(AuditQuery $query, int $limit, int $offset): array
    {
        return array_slice($this->events, $offset, $limit);
    }

    public function count(AuditQuery $query): int
    {
        return count($this->events);
    }
}
