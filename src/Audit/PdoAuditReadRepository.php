<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * Read side of the audit trail (write is the framework recorder, ADR 0014).
 *
 * Reads `audit_events` directly so Clear keeps its own tenant scoping and sort
 * whitelist (including `actor_user_id`). The raw `payload_json` is normalized on
 * hydration: a framework-folded row `{before, after, metadata:{…}}` is flattened
 * back to Clear's historical flat shape `{…context, before, after}`, so folded
 * (post-adoption) and legacy flat rows return an identical payload to the API and
 * the frontend's before/after + context-key rendering.
 */
final readonly class PdoAuditReadRepository implements AuditReadRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, event_type, entity_type, entity_id, actor_user_id, occurred_at, payload_json';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findByOrganization(int $organizationId, AuditEventFilter $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->filter($organizationId, $filter);
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM audit_events WHERE ' . $where
            . ' ORDER BY ' . self::orderBy($filter) . ' LIMIT ? OFFSET ?',
            $params,
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countByOrganization(int $organizationId, AuditEventFilter $filter): int
    {
        [$where, $params] = $this->filter($organizationId, $filter);

        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM audit_events WHERE ' . $where, $params);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array{0: string, 1: list<string|int>}
     */
    private function filter(int $organizationId, AuditEventFilter $filter): array
    {
        $clauses = ['organization_id = ?'];
        /** @var list<string|int> $params */
        $params = [$organizationId];

        if ($filter->eventType !== null) {
            $clauses[] = 'event_type = ?';
            $params[] = $filter->eventType;
        }
        if ($filter->entityType !== null) {
            $clauses[] = 'entity_type = ?';
            $params[] = $filter->entityType;
        }
        if ($filter->entityId !== null) {
            $clauses[] = 'entity_id = ?';
            $params[] = $filter->entityId;
        }
        if ($filter->actorUserId !== null) {
            $clauses[] = 'actor_user_id = ?';
            $params[] = $filter->actorUserId;
        }
        if ($filter->occurredFrom !== null) {
            $clauses[] = 'DATE(occurred_at) >= ?';
            $params[] = $filter->occurredFrom;
        }
        if ($filter->occurredTo !== null) {
            $clauses[] = 'DATE(occurred_at) <= ?';
            $params[] = $filter->occurredTo;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /** Whitelisted ORDER BY — column/direction mapped from a closed set. */
    private static function orderBy(AuditEventFilter $filter): string
    {
        $column = match ($filter->sortColumn) {
            'event_type' => 'event_type',
            'entity_type' => 'entity_type',
            'actor_user_id' => 'actor_user_id',
            default => 'occurred_at',
        };
        $direction = strtolower($filter->sortDirection) === 'asc' ? 'ASC' : 'DESC';

        return $column . ' ' . $direction . ', id DESC';
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
            payload: self::normalize($payload),
            id: (int) $row['id'],
        );
    }

    /**
     * Flattens a framework-folded payload back to Clear's flat shape.
     *
     * The framework's SinglePayload writer stores `{before, after, metadata:{…}}`;
     * Clear's historical rows are flat `{…context, before, after}`. Lifting the
     * `metadata` object's keys to the top level makes both indistinguishable to
     * the API and the frontend (which reads any non-before/after key as context).
     * Legacy flat rows have no top-level `metadata` key and pass through unchanged.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function normalize(array $payload): array
    {
        if (isset($payload['metadata']) && is_array($payload['metadata'])) {
            /** @var array<string, mixed> $metadata */
            $metadata = $payload['metadata'];
            unset($payload['metadata']);
            $payload = array_merge($metadata, $payload);
        }

        return $payload;
    }
}
