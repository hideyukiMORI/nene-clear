<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

interface BankImportBatchRepositoryInterface
{
    public function findById(int $id): ?BankImportBatch;

    public function existsByFileHash(int $organizationId, string $fileHash): bool;

    public function save(BankImportBatch $batch): int;

    /**
     * @return list<BankImportBatch>
     */
    public function findByOrganization(int $organizationId, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId): int;

    public function reverseById(int $id, string $reversedAt, string $reversalReason): void;
}
