<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * Read side of the audit trail (write is the framework recorder, ADR 0014).
 *
 * Reads the canonical `audit_events` columns (stage 2, Issue #258) directly so
 * Clear keeps its own tenant scoping, its `actor_id` sort, and its inclusive
 * `DATE(occurred_at)` bounds — read concerns the framework `AuditQuery`
 * deliberately omits. The stage-1 payload-normalization layer is gone: every
 * row is stored canonically (`before_json` / `after_json` / `metadata_json`),
 * so hydration is a straight column read.
 */
final readonly class PdoAuditReadRepository implements AuditReadRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, action, entity_type, entity_id, actor_id, occurred_at, before_json, after_json, metadata_json';

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

        if ($filter->action !== null) {
            $clauses[] = 'action = ?';
            $params[] = $filter->action;
        }
        if ($filter->entityType !== null) {
            $clauses[] = 'entity_type = ?';
            $params[] = $filter->entityType;
        }
        if ($filter->entityId !== null) {
            $clauses[] = 'entity_id = ?';
            $params[] = $filter->entityId;
        }
        if ($filter->actorId !== null) {
            $clauses[] = 'actor_id = ?';
            $params[] = $filter->actorId;
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
            'action' => 'action',
            'entity_type' => 'entity_type',
            'actor_id' => 'actor_id',
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
        return new AuditEvent(
            organizationId: (int) $row['organization_id'],
            action: (string) $row['action'],
            entityType: (string) $row['entity_type'],
            entityId: $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            actorId: (int) $row['actor_id'],
            occurredAt: (string) $row['occurred_at'],
            before: self::decode($row['before_json'] ?? null),
            after: self::decode($row['after_json'] ?? null),
            metadata: self::decode($row['metadata_json'] ?? null),
            id: (int) $row['id'],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decode(mixed $json): ?array
    {
        if (!is_string($json) || $json === '') {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }
}
