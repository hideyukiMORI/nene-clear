<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

interface ManualReceivableRepositoryInterface
{
    public function findById(int $id): ?ManualReceivable;

    /**
     * @return list<ManualReceivable>
     */
    public function findByOrganization(int $organizationId): array;

    /**
     * The soft-uniqueness / dedupe key for manual entry and CSV import: at most
     * one non-deleted receivable per `(organization_id, reference_number)`.
     */
    public function findByReferenceNumber(int $organizationId, string $referenceNumber): ?ManualReceivable;

    public function save(ManualReceivable $receivable): int;

    /** Soft-delete only — receivables are never hard-deleted (scope-contract X12). */
    public function softDelete(int $id, string $deletedAt): void;
}
