<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

interface BankAccountRepositoryInterface
{
    public function findById(int $id): ?BankAccount;

    public function save(BankAccount $account): int;
}
