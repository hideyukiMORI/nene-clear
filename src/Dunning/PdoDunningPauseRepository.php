<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoDunningPauseRepository implements DunningPauseRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, invoice_id, paused_by, paused_at, '
        . 'paused_reason, unpaused_by, unpaused_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function save(DunningPause $pause): int
    {
        return $this->query->insert(
            'INSERT INTO dunning_pauses (organization_id, invoice_id, paused_by, paused_at, paused_reason) '
            . 'VALUES (?, ?, ?, ?, ?)',
            [
                $pause->organizationId,
                $pause->invoiceId,
                $pause->pausedBy,
                $pause->pausedAt,
                $pause->pausedReason,
            ],
        );
    }

    public function findActiveByInvoice(int $organizationId, int $invoiceId): ?DunningPause
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM dunning_pauses '
            . 'WHERE organization_id = ? AND invoice_id = ? AND unpaused_at IS NULL '
            . 'ORDER BY id DESC LIMIT 1',
            [$organizationId, $invoiceId],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function resumeByInvoice(int $organizationId, int $invoiceId, int $unpausedBy, string $unpausedAt): void
    {
        $this->query->execute(
            'UPDATE dunning_pauses SET unpaused_by = ?, unpaused_at = ? '
            . 'WHERE organization_id = ? AND invoice_id = ? AND unpaused_at IS NULL',
            [$unpausedBy, $unpausedAt, $organizationId, $invoiceId],
        );
    }

    public function findByOrganization(int $organizationId, bool $activeOnly, int $limit, int $offset): array
    {
        $where = $activeOnly
            ? 'WHERE organization_id = ? AND unpaused_at IS NULL'
            : 'WHERE organization_id = ?';

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM dunning_pauses ' . $where
            . ' ORDER BY id DESC LIMIT ? OFFSET ?',
            [$organizationId, $limit, $offset],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countByOrganization(int $organizationId, bool $activeOnly): int
    {
        $where = $activeOnly
            ? 'WHERE organization_id = ? AND unpaused_at IS NULL'
            : 'WHERE organization_id = ?';

        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS c FROM dunning_pauses ' . $where,
            [$organizationId],
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): DunningPause
    {
        return new DunningPause(
            organizationId: (int) $row['organization_id'],
            invoiceId: (int) $row['invoice_id'],
            pausedBy: (int) $row['paused_by'],
            pausedAt: (string) $row['paused_at'],
            pausedReason: (string) $row['paused_reason'],
            unpausedBy: $row['unpaused_by'] !== null ? (int) $row['unpaused_by'] : null,
            unpausedAt: $row['unpaused_at'] !== null ? (string) $row['unpaused_at'] : null,
            id: (int) $row['id'],
        );
    }
}
