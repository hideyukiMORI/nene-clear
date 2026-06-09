<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoBankImportBatchRepository implements BankImportBatchRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, bank_account_id, file_hash, source_filename, row_count, '
        . 'status, imported_by, imported_at, reversed_at, reversal_reason';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findById(int $id): ?BankImportBatch
    {
        $row = $this->query->fetchOne('SELECT ' . self::COLUMNS . ' FROM bank_import_batches WHERE id = ?', [$id]);

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function existsByFileHash(int $organizationId, string $fileHash): bool
    {
        return $this->query->fetchOne(
            'SELECT 1 FROM bank_import_batches WHERE organization_id = ? AND file_hash = ?',
            [$organizationId, $fileHash],
        ) !== null;
    }

    public function save(BankImportBatch $batch): int
    {
        return $this->query->insert(
            'INSERT INTO bank_import_batches (organization_id, bank_account_id, file_hash, source_filename, '
            . 'row_count, status, imported_by, imported_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $batch->organizationId,
                $batch->bankAccountId,
                $batch->fileHash,
                $batch->sourceFilename,
                $batch->rowCount,
                $batch->status->value,
                $batch->importedBy,
                $batch->importedAt,
            ],
        );
    }

    public function findByOrganization(int $organizationId, BankImportBatchFilter $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->whereClause($organizationId, $filter);
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM bank_import_batches WHERE ' . $where
            . ' ORDER BY ' . self::orderBy($filter) . ' LIMIT ? OFFSET ?',
            $params,
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countByOrganization(int $organizationId, BankImportBatchFilter $filter): int
    {
        [$where, $params] = $this->whereClause($organizationId, $filter);
        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM bank_import_batches WHERE ' . $where, $params);

        if ($row === null) {
            return 0;
        }

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array{0: string, 1: list<string|int>}
     */
    private function whereClause(int $organizationId, BankImportBatchFilter $filter): array
    {
        $clauses = ['organization_id = ?'];
        /** @var list<string|int> $params */
        $params = [$organizationId];

        if ($filter->sourceFilename !== null) {
            $clauses[] = 'source_filename LIKE ?';
            $params[] = '%' . $filter->sourceFilename . '%';
        }
        if ($filter->status !== null) {
            $clauses[] = 'status = ?';
            $params[] = $filter->status->value;
        }
        if ($filter->rowCountMin !== null) {
            $clauses[] = 'row_count >= ?';
            $params[] = $filter->rowCountMin;
        }
        if ($filter->rowCountMax !== null) {
            $clauses[] = 'row_count <= ?';
            $params[] = $filter->rowCountMax;
        }
        if ($filter->importedFrom !== null) {
            $clauses[] = 'DATE(imported_at) >= ?';
            $params[] = $filter->importedFrom;
        }
        if ($filter->importedTo !== null) {
            $clauses[] = 'DATE(imported_at) <= ?';
            $params[] = $filter->importedTo;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /** Whitelisted ORDER BY — column/direction mapped from a closed set. */
    private static function orderBy(BankImportBatchFilter $filter): string
    {
        $column = match ($filter->sortColumn) {
            'source_filename' => 'source_filename',
            'row_count' => 'row_count',
            'status' => 'status',
            'imported_at' => 'imported_at',
            default => 'id',
        };
        $direction = strtolower($filter->sortDirection) === 'asc' ? 'ASC' : 'DESC';

        return $column . ' ' . $direction . ', id DESC';
    }

    public function reverseById(int $id, string $reversedAt, string $reversalReason): void
    {
        $this->query->execute(
            'UPDATE bank_import_batches SET status = ?, reversed_at = ?, reversal_reason = ? WHERE id = ?',
            [BankImportBatchStatus::Reversed->value, $reversedAt, $reversalReason, $id],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): BankImportBatch
    {
        return new BankImportBatch(
            organizationId: (int) $row['organization_id'],
            bankAccountId: (int) $row['bank_account_id'],
            fileHash: (string) $row['file_hash'],
            sourceFilename: (string) $row['source_filename'],
            rowCount: (int) $row['row_count'],
            status: BankImportBatchStatus::from((string) $row['status']),
            importedBy: (int) $row['imported_by'],
            importedAt: (string) $row['imported_at'],
            id: (int) $row['id'],
            reversedAt: isset($row['reversed_at']) ? (string) $row['reversed_at'] : null,
            reversalReason: isset($row['reversal_reason']) ? (string) $row['reversal_reason'] : null,
        );
    }
}
