<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

interface BankTransactionRepositoryInterface
{
    public function save(BankTransaction $transaction): int;

    public function countByBatch(int $bankImportBatchId): int;

    /**
     * @return list<BankTransaction>
     */
    public function findByBatch(int $bankImportBatchId): array;
}
