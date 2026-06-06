<?php

declare(strict_types=1);

namespace NeneClear\Tests\Audit;

use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventFilter;
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
            entityType: $event->entityType,
            entityId: $event->entityId,
            actorUserId: $event->actorUserId,
            occurredAt: $event->occurredAt,
            payload: $event->payload,
            id: $id,
        );

        return $id;
    }

    public function findByOrganization(int $organizationId, AuditEventFilter $filter, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->events,
            fn (AuditEvent $e): bool => $this->matches($e, $organizationId, $filter),
        ));

        $asc = strtolower($filter->sortDirection) === 'asc';
        usort($matches, function (AuditEvent $a, AuditEvent $b) use ($filter, $asc): int {
            $r = $this->sortKey($a, $filter->sortColumn) <=> $this->sortKey($b, $filter->sortColumn);
            $r = $asc ? $r : -$r;
            return $r !== 0 ? $r : (($b->id ?? 0) <=> ($a->id ?? 0));
        });

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId, AuditEventFilter $filter): int
    {
        return count(array_filter(
            $this->events,
            fn (AuditEvent $e): bool => $this->matches($e, $organizationId, $filter),
        ));
    }

    private function matches(AuditEvent $e, int $organizationId, AuditEventFilter $f): bool
    {
        if ($e->organizationId !== $organizationId) {
            return false;
        }
        if ($f->eventType !== null && $e->eventType !== $f->eventType) {
            return false;
        }
        if ($f->entityType !== null && $e->entityType !== $f->entityType) {
            return false;
        }
        if ($f->entityId !== null && $e->entityId !== $f->entityId) {
            return false;
        }
        if ($f->actorUserId !== null && $e->actorUserId !== $f->actorUserId) {
            return false;
        }
        $date = substr($e->occurredAt, 0, 10);
        if ($f->occurredFrom !== null && $date < $f->occurredFrom) {
            return false;
        }
        if ($f->occurredTo !== null && $date > $f->occurredTo) {
            return false;
        }

        return true;
    }

    private function sortKey(AuditEvent $e, string $column): int|string
    {
        return match ($column) {
            'event_type' => $e->eventType,
            'entity_type' => $e->entityType,
            'actor_user_id' => $e->actorUserId,
            default => $e->occurredAt,
        };
    }
}
