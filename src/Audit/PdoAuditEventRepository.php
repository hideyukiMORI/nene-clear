<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoAuditEventRepository implements AuditEventRepositoryInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function record(AuditEvent $event): int
    {
        $this->query->execute(
            'INSERT INTO audit_events (organization_id, event_type, actor_user_id, occurred_at, payload_json) '
            . 'VALUES (?, ?, ?, ?, ?)',
            [
                $event->organizationId,
                $event->eventType,
                $event->actorUserId,
                $event->occurredAt,
                json_encode($event->payload, JSON_THROW_ON_ERROR),
            ],
        );

        return $this->query->lastInsertId();
    }
}
