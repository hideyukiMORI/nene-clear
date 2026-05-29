<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoBankImportBatchRepository implements BankImportBatchRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, bank_account_id, file_hash, source_filename, row_count, '
        . 'status, imported_by, imported_at';

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
        $this->query->execute(
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

        return $this->query->lastInsertId();
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
        );
    }
}
