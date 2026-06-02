<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoAuditEventRepository implements AuditEventRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, event_type, actor_user_id, occurred_at, payload_json';

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

    public function findByOrganization(int $organizationId, ?string $eventType, int $limit, int $offset): array
    {
        if ($eventType === null) {
            $rows = $this->query->fetchAll(
                'SELECT ' . self::COLUMNS . ' FROM audit_events WHERE organization_id = ? '
                . 'ORDER BY id DESC LIMIT ? OFFSET ?',
                [$organizationId, $limit, $offset],
            );
        } else {
            $rows = $this->query->fetchAll(
                'SELECT ' . self::COLUMNS . ' FROM audit_events WHERE organization_id = ? AND event_type = ? '
                . 'ORDER BY id DESC LIMIT ? OFFSET ?',
                [$organizationId, $eventType, $limit, $offset],
            );
        }

        return array_map($this->hydrate(...), $rows);
    }

    public function countByOrganization(int $organizationId, ?string $eventType): int
    {
        $row = $eventType === null
            ? $this->query->fetchOne('SELECT COUNT(*) AS c FROM audit_events WHERE organization_id = ?', [$organizationId])
            : $this->query->fetchOne('SELECT COUNT(*) AS c FROM audit_events WHERE organization_id = ? AND event_type = ?', [$organizationId, $eventType]);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AuditEvent
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $row['payload_json'], true, 512, JSON_THROW_ON_ERROR);

        return new AuditEvent(
            organizationId: (int) $row['organization_id'],
            eventType: (string) $row['event_type'],
            actorUserId: (int) $row['actor_user_id'],
            occurredAt: (string) $row['occurred_at'],
            payload: $payload,
            id: (int) $row['id'],
        );
    }
}
