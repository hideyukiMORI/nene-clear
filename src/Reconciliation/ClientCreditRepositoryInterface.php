<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

interface ClientCreditRepositoryInterface
{
    public function save(ClientCredit $credit): int;

    public function findById(int $organizationId, int $id): ?ClientCredit;

    public function findByReconciliation(int $organizationId, int $reconciliationId): ?ClientCredit;

    public function applyAmount(int $organizationId, int $id, int $amountCents): ClientCredit;

    public function voidByReconciliation(int $reconciliationId): void;

    /**
     * @return list<ClientCredit>
     */
    public function findByOrganization(int $organizationId, ClientCreditFilter $filter, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId, ClientCreditFilter $filter): int;
}
