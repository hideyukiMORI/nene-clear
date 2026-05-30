<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

interface BankTransactionRepositoryInterface
{
    public function save(BankTransaction $transaction): int;

    public function findById(int $organizationId, int $id): ?BankTransaction;

    public function countByBatch(int $bankImportBatchId): int;

    /**
     * @return list<BankTransaction>
     */
    public function findByBatch(int $bankImportBatchId): array;

    /**
     * @return list<BankTransaction>
     */
    public function findByOrganization(int $organizationId, BankTransactionFilter $filter, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId, BankTransactionFilter $filter): int;

    /**
     * Lines still open for matching (unmatched or partially matched).
     *
     * @return list<BankTransaction>
     */
    public function findUnmatchedByOrganization(int $organizationId, int $limit, int $offset): array;

    public function countUnmatchedByOrganization(int $organizationId): int;

    public function updateStatusById(int $organizationId, int $id, BankTransactionStatus $status): void;

    public function voidByBatchId(int $bankImportBatchId): void;
}
