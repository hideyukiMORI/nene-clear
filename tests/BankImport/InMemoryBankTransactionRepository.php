<?php

declare(strict_types=1);

namespace NeneClear\Tests\BankImport;

use NeneClear\BankImport\BankTransaction;
use NeneClear\BankImport\BankTransactionFilter;
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

    public function findById(int $organizationId, int $id): ?BankTransaction
    {
        $tx = $this->byId[$id] ?? null;

        return ($tx !== null && $tx->organizationId === $organizationId) ? $tx : null;
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

    public function findByOrganization(int $organizationId, BankTransactionFilter $filter, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            fn (BankTransaction $t): bool => $this->matchesFilter($t, $organizationId, $filter),
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId, BankTransactionFilter $filter): int
    {
        return count(array_filter(
            $this->byId,
            fn (BankTransaction $t): bool => $this->matchesFilter($t, $organizationId, $filter),
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

    public function updateStatusById(int $id, BankTransactionStatus $status): void
    {
        $tx = $this->byId[$id] ?? null;
        if ($tx === null) {
            return;
        }

        $this->byId[$id] = new BankTransaction(
            organizationId: $tx->organizationId,
            bankImportBatchId: $tx->bankImportBatchId,
            bankAccountId: $tx->bankAccountId,
            valueDate: $tx->valueDate,
            amountCents: $tx->amountCents,
            counterpartyText: $tx->counterpartyText,
            lineKey: $tx->lineKey,
            status: $status,
            id: $tx->id,
        );
    }

    public function voidByBatchId(int $bankImportBatchId): void
    {
        foreach ($this->byId as $id => $tx) {
            if ($tx->bankImportBatchId === $bankImportBatchId && $tx->status === BankTransactionStatus::Unmatched) {
                $this->byId[$id] = new BankTransaction(
                    organizationId: $tx->organizationId,
                    bankImportBatchId: $tx->bankImportBatchId,
                    bankAccountId: $tx->bankAccountId,
                    valueDate: $tx->valueDate,
                    amountCents: $tx->amountCents,
                    counterpartyText: $tx->counterpartyText,
                    lineKey: $tx->lineKey,
                    status: BankTransactionStatus::Voided,
                    id: $tx->id,
                );
            }
        }
    }

    private function matchesFilter(BankTransaction $t, int $organizationId, BankTransactionFilter $filter): bool
    {
        if ($t->organizationId !== $organizationId) {
            return false;
        }
        if ($filter->status !== null && $t->status !== $filter->status) {
            return false;
        }
        if ($filter->valueDateFrom !== null && $t->valueDate < $filter->valueDateFrom) {
            return false;
        }
        if ($filter->valueDateTo !== null && $t->valueDate > $filter->valueDateTo) {
            return false;
        }
        if ($filter->amountMinCents !== null && $t->amountCents < $filter->amountMinCents) {
            return false;
        }
        if ($filter->amountMaxCents !== null && $t->amountCents > $filter->amountMaxCents) {
            return false;
        }
        if ($filter->counterparty !== null && $filter->counterparty !== ''
            && stripos($t->counterpartyText, $filter->counterparty) === false) {
            return false;
        }

        return true;
    }
}
