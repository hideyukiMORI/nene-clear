<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoManualReceivableRepository implements ManualReceivableRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, reference_number, client_name, recipient_email, '
        . 'total_cents, outstanding_cents, currency, issued_at, due_at, status, created_by, created_at, updated_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findById(int $id): ?ManualReceivable
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM manual_receivables WHERE id = ? AND is_deleted = 0',
            [$id],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByOrganization(int $organizationId, ManualReceivableFilter $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->whereClause($organizationId, $filter);
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM manual_receivables WHERE ' . $where
            . ' ORDER BY ' . self::orderBy($filter) . ' LIMIT ? OFFSET ?',
            $params,
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countByOrganization(int $organizationId, ManualReceivableFilter $filter): int
    {
        [$where, $params] = $this->whereClause($organizationId, $filter);
        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM manual_receivables WHERE ' . $where, $params);

        return (int) ($row['c'] ?? 0);
    }

    public function findByReferenceNumber(int $organizationId, string $referenceNumber): ?ManualReceivable
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM manual_receivables '
            . 'WHERE organization_id = ? AND reference_number = ? AND is_deleted = 0',
            [$organizationId, $referenceNumber],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function save(ManualReceivable $receivable): int
    {
        $this->query->execute(
            'INSERT INTO manual_receivables (organization_id, reference_number, client_name, recipient_email, '
            . 'total_cents, outstanding_cents, currency, issued_at, due_at, status, created_by, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $receivable->organizationId,
                $receivable->referenceNumber,
                $receivable->clientName,
                $receivable->recipientEmail,
                $receivable->totalCents,
                $receivable->outstandingCents,
                $receivable->currency,
                $receivable->issuedAt,
                $receivable->dueAt,
                $receivable->status->value,
                $receivable->createdBy,
                $receivable->createdAt,
                $receivable->updatedAt ?? $receivable->createdAt,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function update(ManualReceivable $receivable): void
    {
        $this->query->execute(
            'UPDATE manual_receivables SET reference_number = ?, client_name = ?, recipient_email = ?, '
            . 'total_cents = ?, outstanding_cents = ?, currency = ?, issued_at = ?, due_at = ?, status = ?, updated_at = ? '
            . 'WHERE id = ? AND is_deleted = 0',
            [
                $receivable->referenceNumber,
                $receivable->clientName,
                $receivable->recipientEmail,
                $receivable->totalCents,
                $receivable->outstandingCents,
                $receivable->currency,
                $receivable->issuedAt,
                $receivable->dueAt,
                $receivable->status->value,
                $receivable->updatedAt ?? $receivable->createdAt,
                $receivable->id,
            ],
        );
    }

    public function softDelete(int $id, string $deletedAt): void
    {
        $this->query->execute(
            'UPDATE manual_receivables SET is_deleted = 1, deleted_at = ? WHERE id = ? AND is_deleted = 0',
            [$deletedAt, $id],
        );
    }

    /**
     * @return array{0: string, 1: list<string|int>}
     */
    private function whereClause(int $organizationId, ManualReceivableFilter $filter): array
    {
        $clauses = ['organization_id = ?', 'is_deleted = 0'];
        /** @var list<string|int> $params */
        $params = [$organizationId];

        if ($filter->status !== null) {
            $clauses[] = 'status = ?';
            $params[] = $filter->status->value;
        }
        if ($filter->q !== null) {
            $clauses[] = '(reference_number LIKE ? OR client_name LIKE ?)';
            $like = '%' . $filter->q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($filter->dueFrom !== null) {
            $clauses[] = 'due_at >= ?';
            $params[] = $filter->dueFrom;
        }
        if ($filter->dueTo !== null) {
            $clauses[] = 'due_at <= ?';
            $params[] = $filter->dueTo;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Whitelisted ORDER BY — column and direction map from a closed set, so user
     * input is never interpolated into SQL.
     */
    private static function orderBy(ManualReceivableFilter $filter): string
    {
        $column = match ($filter->sortColumn) {
            'due_at' => 'due_at',
            'total_cents' => 'total_cents',
            'outstanding_cents' => 'outstanding_cents',
            'created_at' => 'created_at',
            default => 'id',
        };
        $direction = strtolower($filter->sortDirection) === 'asc' ? 'ASC' : 'DESC';

        return $column . ' ' . $direction . ', id DESC';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ManualReceivable
    {
        return new ManualReceivable(
            organizationId: (int) $row['organization_id'],
            referenceNumber: (string) $row['reference_number'],
            clientName: (string) $row['client_name'],
            recipientEmail: $row['recipient_email'] !== null ? (string) $row['recipient_email'] : null,
            totalCents: (int) $row['total_cents'],
            outstandingCents: (int) $row['outstanding_cents'],
            currency: (string) $row['currency'],
            issuedAt: $row['issued_at'] !== null ? (string) $row['issued_at'] : null,
            dueAt: $row['due_at'] !== null ? (string) $row['due_at'] : null,
            status: ManualReceivableStatus::from((string) $row['status']),
            createdBy: (int) $row['created_by'],
            createdAt: (string) $row['created_at'],
            updatedAt: $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
            id: (int) $row['id'],
        );
    }
}
