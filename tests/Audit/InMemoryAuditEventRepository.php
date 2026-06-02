<?php

declare(strict_types=1);

namespace NeneClear\Tests\Audit;

use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final class InMemoryAuditEventRepository implements AuditEventRepositoryInterface
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function record(AuditEvent $event): int
    {
        $id = count($this->events) + 1;
        $this->events[] = new AuditEvent(
            organizationId: $event->organizationId,
            eventType: $event->eventType,
            actorUserId: $event->actorUserId,
            occurredAt: $event->occurredAt,
            payload: $event->payload,
            id: $id,
        );

        return $id;
    }

    public function findByOrganization(int $organizationId, ?string $eventType, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->events,
            static fn (AuditEvent $e): bool => $e->organizationId === $organizationId
                && ($eventType === null || $e->eventType === $eventType),
        ));

        // Newest first, mirroring the SQL `ORDER BY id DESC`.
        usort($matches, static fn (AuditEvent $a, AuditEvent $b): int => ($b->id ?? 0) <=> ($a->id ?? 0));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId, ?string $eventType): int
    {
        return count(array_filter(
            $this->events,
            static fn (AuditEvent $e): bool => $e->organizationId === $organizationId
                && ($eventType === null || $e->eventType === $eventType),
        ));
    }
}
