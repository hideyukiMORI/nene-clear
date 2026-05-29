<?php

declare(strict_types=1);

namespace NeneClear\Tests\BankImport;

use NeneClear\BankImport\BankTransaction;
use NeneClear\BankImport\BankTransactionRepositoryInterface;

final class InMemoryBankTransactionRepository implements BankTransactionRepositoryInterface
{
    /** @var array<int, BankTransaction> */
    private array $byId = [];

    private int $nextId = 1;

    public function save(BankTransaction $transaction): int
    {
        $id = $transaction->id ?? $this->nextId++;
        $this->byId[$id] = new BankTransaction(
            organizationId: $transaction->organizationId,
            bankImportBatchId: $transaction->bankImportBatchId,
            bankAccountId: $transaction->bankAccountId,
            valueDate: $transaction->valueDate,
            amountCents: $transaction->amountCents,
            counterpartyText: $transaction->counterpartyText,
            lineKey: $transaction->lineKey,
            status: $transaction->status,
            id: $id,
        );

        return $id;
    }

    public function countByBatch(int $bankImportBatchId): int
    {
        return count($this->findByBatch($bankImportBatchId));
    }

    public function findByBatch(int $bankImportBatchId): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (BankTransaction $t): bool => $t->bankImportBatchId === $bankImportBatchId,
        ));
    }
}
