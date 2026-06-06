<?php

declare(strict_types=1);

namespace NeneClear\Tests\BankImport;

use NeneClear\BankImport\BankImportBatch;
use NeneClear\BankImport\BankImportBatchFilter;
use NeneClear\BankImport\BankImportBatchRepositoryInterface;
use NeneClear\BankImport\BankImportBatchStatus;

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

    public function findByOrganization(int $organizationId, BankImportBatchFilter $filter, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (BankImportBatch $b): bool => $b->organizationId === $organizationId,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId, BankImportBatchFilter $filter): int
    {
        return count(array_filter(
            $this->byId,
            static fn (BankImportBatch $b): bool => $b->organizationId === $organizationId,
        ));
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
            reversedAt: $batch->reversedAt,
            reversalReason: $batch->reversalReason,
        );

        return $id;
    }

    public function reverseById(int $id, string $reversedAt, string $reversalReason): void
    {
        $batch = $this->byId[$id] ?? null;
        if ($batch === null) {
            return;
        }

        $this->byId[$id] = new BankImportBatch(
            organizationId: $batch->organizationId,
            bankAccountId: $batch->bankAccountId,
            fileHash: $batch->fileHash,
            sourceFilename: $batch->sourceFilename,
            rowCount: $batch->rowCount,
            status: BankImportBatchStatus::Reversed,
            importedBy: $batch->importedBy,
            importedAt: $batch->importedAt,
            id: $batch->id,
            reversedAt: $reversedAt,
            reversalReason: $reversalReason,
        );
    }
}
