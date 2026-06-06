<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoAuditEventRepository implements AuditEventRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, event_type, entity_type, entity_id, actor_user_id, occurred_at, payload_json';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function record(AuditEvent $event): int
    {
        $this->query->execute(
            'INSERT INTO audit_events (organization_id, event_type, entity_type, entity_id, actor_user_id, occurred_at, payload_json) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $event->organizationId,
                $event->eventType,
                $event->entityType,
                $event->entityId,
                $event->actorUserId,
                $event->occurredAt,
                json_encode($event->payload, JSON_THROW_ON_ERROR),
            ],
        );

        return $this->query->lastInsertId();
    }

    public function findByOrganization(
        int $organizationId,
        ?string $eventType,
        ?string $entityType,
        ?int $entityId,
        int $limit,
        int $offset,
    ): array {
        [$where, $params] = $this->filter($organizationId, $eventType, $entityType, $entityId);
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM audit_events WHERE ' . $where
            . ' ORDER BY id DESC LIMIT ? OFFSET ?',
            $params,
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countByOrganization(
        int $organizationId,
        ?string $eventType,
        ?string $entityType,
        ?int $entityId,
    ): int {
        [$where, $params] = $this->filter($organizationId, $eventType, $entityType, $entityId);

        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM audit_events WHERE ' . $where, $params);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array{0: string, 1: list<string|int>}
     */
    private function filter(int $organizationId, ?string $eventType, ?string $entityType, ?int $entityId): array
    {
        $clauses = ['organization_id = ?'];
        /** @var list<string|int> $params */
        $params = [$organizationId];

        if ($eventType !== null) {
            $clauses[] = 'event_type = ?';
            $params[] = $eventType;
        }
        if ($entityType !== null) {
            $clauses[] = 'entity_type = ?';
            $params[] = $entityType;
        }
        if ($entityId !== null) {
            $clauses[] = 'entity_id = ?';
            $params[] = $entityId;
        }

        return [implode(' AND ', $clauses), $params];
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
            entityType: (string) $row['entity_type'],
            entityId: $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            actorUserId: (int) $row['actor_user_id'],
            occurredAt: (string) $row['occurred_at'],
            payload: $payload,
            id: (int) $row['id'],
        );
    }
}
