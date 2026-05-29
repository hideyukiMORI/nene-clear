<?php

declare(strict_types=1);

namespace NeneClear\Tests\BankImport;

use NeneClear\BankImport\BankAccount;
use NeneClear\BankImport\BankAccountRepositoryInterface;

final class InMemoryBankAccountRepository implements BankAccountRepositoryInterface
{
    /** @var array<int, BankAccount> */
    private array $byId = [];

    private int $nextId = 1;

    public function findById(int $id): ?BankAccount
    {
        return $this->byId[$id] ?? null;
    }

    public function save(BankAccount $account): int
    {
        $id = $account->id ?? $this->nextId++;
        $this->byId[$id] = new BankAccount(
            organizationId: $account->organizationId,
            bankName: $account->bankName,
            bankBranch: $account->bankBranch,
            accountType: $account->accountType,
            accountNumber: $account->accountNumber,
            csvEncoding: $account->csvEncoding,
            csvDateFormat: $account->csvDateFormat,
            csvDateColumn: $account->csvDateColumn,
            csvAmountColumn: $account->csvAmountColumn,
            csvCounterpartyColumn: $account->csvCounterpartyColumn,
            csvHeaderRows: $account->csvHeaderRows,
            id: $id,
        );

        return $id;
    }
}
