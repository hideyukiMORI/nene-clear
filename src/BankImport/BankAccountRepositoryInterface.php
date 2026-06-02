<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

interface BankAccountRepositoryInterface
{
    public function findById(int $id): ?BankAccount;

    /**
     * @return list<BankAccount>
     */
    public function findByOrganization(int $organizationId): array;

    public function save(BankAccount $account): int;

    public function deleteByOrganization(int $organizationId, string $deletedAt): void;
}
