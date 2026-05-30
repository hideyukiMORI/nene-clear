<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

interface ReconciliationRepositoryInterface
{
    public function findById(int $organizationId, int $id): ?Reconciliation;

    public function findByIdempotencyKey(int $organizationId, string $key): ?Reconciliation;

    /**
     * @return list<Reconciliation>
     */
    public function findByOrganization(int $organizationId, ?ReconciliationStatus $status, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId, ?ReconciliationStatus $status): int;

    public function save(Reconciliation $reconciliation): int;

    public function saveAllocation(ReconciliationAllocation $allocation): int;

    /**
     * @return list<ReconciliationAllocation>
     */
    public function findAllocationsByReconciliation(int $reconciliationId): array;

    public function reverseById(int $id, string $reversedAt, string $reversalReason): void;
}
