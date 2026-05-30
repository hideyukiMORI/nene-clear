<?php

declare(strict_types=1);

namespace NeneClear\Tests\Reconciliation;

use NeneClear\Reconciliation\Reconciliation;
use NeneClear\Reconciliation\ReconciliationAllocation;
use NeneClear\Reconciliation\ReconciliationRepositoryInterface;
use NeneClear\Reconciliation\ReconciliationStatus;

final class InMemoryReconciliationRepository implements ReconciliationRepositoryInterface
{
    /** @var array<int, Reconciliation> */
    private array $byId = [];

    /** @var array<int, list<ReconciliationAllocation>> keyed by reconciliationId */
    private array $allocations = [];

    private int $nextId = 1;
    private int $nextAllocId = 1;

    public function findById(int $organizationId, int $id): ?Reconciliation
    {
        $r = $this->byId[$id] ?? null;

        return ($r !== null && $r->organizationId === $organizationId) ? $r : null;
    }

    public function findByIdempotencyKey(int $organizationId, string $key): ?Reconciliation
    {
        foreach ($this->byId as $r) {
            if ($r->organizationId === $organizationId && $r->idempotencyKey === $key) {
                return $r;
            }
        }

        return null;
    }

    public function findByOrganization(int $organizationId, ?ReconciliationStatus $status, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (Reconciliation $r): bool => $r->organizationId === $organizationId
                && ($status === null || $r->status === $status),
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId, ?ReconciliationStatus $status): int
    {
        return count(array_filter(
            $this->byId,
            static fn (Reconciliation $r): bool => $r->organizationId === $organizationId
                && ($status === null || $r->status === $status),
        ));
    }

    public function save(Reconciliation $reconciliation): int
    {
        $id = $reconciliation->id ?? $this->nextId++;
        $this->byId[$id] = new Reconciliation(
            organizationId: $reconciliation->organizationId,
            bankTransactionId: $reconciliation->bankTransactionId,
            status: $reconciliation->status,
            confirmedBy: $reconciliation->confirmedBy,
            confirmedAt: $reconciliation->confirmedAt,
            reasonCode: $reconciliation->reasonCode,
            reversedAt: $reconciliation->reversedAt,
            reversalReason: $reconciliation->reversalReason,
            idempotencyKey: $reconciliation->idempotencyKey,
            id: $id,
        );

        return $id;
    }

    public function saveAllocation(ReconciliationAllocation $allocation): int
    {
        $id = $this->nextAllocId++;
        $this->allocations[$allocation->reconciliationId][] = new ReconciliationAllocation(
            organizationId: $allocation->organizationId,
            reconciliationId: $allocation->reconciliationId,
            invoiceId: $allocation->invoiceId,
            amountCents: $allocation->amountCents,
            paymentId: $allocation->paymentId,
            externalReference: $allocation->externalReference,
            id: $id,
        );

        return $id;
    }

    public function findAllocationsByReconciliation(int $reconciliationId): array
    {
        return $this->allocations[$reconciliationId] ?? [];
    }

    public function reverseById(int $id, string $reversedAt, string $reversalReason): void
    {
        $r = $this->byId[$id] ?? null;
        if ($r === null) {
            return;
        }

        $this->byId[$id] = new Reconciliation(
            organizationId: $r->organizationId,
            bankTransactionId: $r->bankTransactionId,
            status: ReconciliationStatus::Reversed,
            confirmedBy: $r->confirmedBy,
            confirmedAt: $r->confirmedAt,
            reasonCode: $r->reasonCode,
            reversedAt: $reversedAt,
            reversalReason: $reversalReason,
            id: $r->id,
        );
    }
}
