<?php

declare(strict_types=1);

namespace NeneClear\Tests\Reconciliation;

use NeneClear\Receivable\ManualReceivable;
use NeneClear\Receivable\ManualReceivableFilter;
use NeneClear\Receivable\ManualReceivableRepositoryInterface;

/** In-memory ManualReceivable repository for use-case unit tests. */
final class InMemoryManualReceivableRepository implements ManualReceivableRepositoryInterface
{
    /** @var array<int, ManualReceivable> */
    private array $rows = [];
    private int $nextId = 1;

    public function findById(int $id): ?ManualReceivable
    {
        return array_key_exists($id, $this->rows) ? $this->rows[$id] : null;
    }

    public function findByOrganization(int $organizationId, ManualReceivableFilter $filter, int $limit, int $offset): array
    {
        $items = array_values(array_filter($this->rows, static fn (ManualReceivable $r): bool => $r->organizationId === $organizationId));

        return array_slice($items, $offset, $limit);
    }

    public function countByOrganization(int $organizationId, ManualReceivableFilter $filter): int
    {
        return count(array_filter($this->rows, static fn (ManualReceivable $r): bool => $r->organizationId === $organizationId));
    }

    public function findByReferenceNumber(int $organizationId, string $referenceNumber): ?ManualReceivable
    {
        foreach ($this->rows as $r) {
            if ($r->organizationId === $organizationId && $r->referenceNumber === $referenceNumber) {
                return $r;
            }
        }

        return null;
    }

    public function save(ManualReceivable $receivable): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = $this->withId($receivable, $id);

        return $id;
    }

    public function update(ManualReceivable $receivable): void
    {
        if ($receivable->id !== null && isset($this->rows[$receivable->id])) {
            $this->rows[$receivable->id] = $receivable;
        }
    }

    public function softDelete(int $id, string $deletedAt): void
    {
        unset($this->rows[$id]);
    }

    private function withId(ManualReceivable $r, int $id): ManualReceivable
    {
        return new ManualReceivable(
            organizationId: $r->organizationId,
            referenceNumber: $r->referenceNumber,
            clientName: $r->clientName,
            recipientEmail: $r->recipientEmail,
            totalCents: $r->totalCents,
            outstandingCents: $r->outstandingCents,
            currency: $r->currency,
            issuedAt: $r->issuedAt,
            dueAt: $r->dueAt,
            status: $r->status,
            createdBy: $r->createdBy,
            createdAt: $r->createdAt,
            updatedAt: $r->updatedAt,
            id: $id,
        );
    }
}
