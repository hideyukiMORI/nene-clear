<?php

declare(strict_types=1);

namespace NeneClear\Tests\BankImport;

use NeneClear\BankImport\BankImportBatch;
use NeneClear\BankImport\BankImportBatchRepositoryInterface;

final class InMemoryBankImportBatchRepository implements BankImportBatchRepositoryInterface
{
    /** @var array<int, BankImportBatch> */
    private array $byId = [];

    private int $nextId = 1;

    public function findById(int $id): ?BankImportBatch
    {
        return $this->byId[$id] ?? null;
    }

    public function existsByFileHash(int $organizationId, string $fileHash): bool
    {
        foreach ($this->byId as $batch) {
            if ($batch->organizationId === $organizationId && $batch->fileHash === $fileHash) {
                return true;
            }
        }

        return false;
    }

    public function save(BankImportBatch $batch): int
    {
        $id = $batch->id ?? $this->nextId++;
        $this->byId[$id] = new BankImportBatch(
            organizationId: $batch->organizationId,
            bankAccountId: $batch->bankAccountId,
            fileHash: $batch->fileHash,
            sourceFilename: $batch->sourceFilename,
            rowCount: $batch->rowCount,
            status: $batch->status,
            importedBy: $batch->importedBy,
            importedAt: $batch->importedAt,
            id: $id,
        );

        return $id;
    }
}
