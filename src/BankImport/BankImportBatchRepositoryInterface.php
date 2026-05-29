<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

interface BankImportBatchRepositoryInterface
{
    public function findById(int $id): ?BankImportBatch;

    public function existsByFileHash(int $organizationId, string $fileHash): bool;

    public function save(BankImportBatch $batch): int;
}
