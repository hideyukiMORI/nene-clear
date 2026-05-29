<?php

declare(strict_types=1);

namespace NeneClear\Tests\BankImport;

use NeneClear\BankImport\BankTransaction;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\BankImport\BankTransactionStatus;

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

    public function findByOrganization(int $organizationId, ?BankTransactionStatus $status, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (BankTransaction $t): bool => $t->organizationId === $organizationId
                && ($status === null || $t->status === $status),
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId, ?BankTransactionStatus $status): int
    {
        return count(array_filter(
            $this->byId,
            static fn (BankTransaction $t): bool => $t->organizationId === $organizationId
                && ($status === null || $t->status === $status),
        ));
    }

    public function findUnmatchedByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (BankTransaction $t): bool => $t->organizationId === $organizationId
                && in_array($t->status, [BankTransactionStatus::Unmatched, BankTransactionStatus::PartiallyMatched], true),
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countUnmatchedByOrganization(int $organizationId): int
    {
        return count(array_filter(
            $this->byId,
            static fn (BankTransaction $t): bool => $t->organizationId === $organizationId
                && in_array($t->status, [BankTransactionStatus::Unmatched, BankTransactionStatus::PartiallyMatched], true),
        ));
    }
}
