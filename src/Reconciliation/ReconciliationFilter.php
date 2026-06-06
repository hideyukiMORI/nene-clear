<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

/**
 * Server-side filter + sort for the reconciliation history list (Issue #130).
 * Every field is optional; null/empty means "no constraint". `invoiceId` matches
 * reconciliations that have an allocation to that invoice. `sortColumn` is
 * validated against a whitelist in the repository.
 *
 * `confirmedFrom` / `confirmedTo` are inclusive `YYYY-MM-DD` bounds on
 * `DATE(confirmed_at)`.
 */
final readonly class ReconciliationFilter
{
    public function __construct(
        public ?ReconciliationStatus $status = null,
        public ?int $bankTransactionId = null,
        public ?int $invoiceId = null,
        public ?string $confirmedFrom = null,
        public ?string $confirmedTo = null,
        public string $sortColumn = 'id',
        public string $sortDirection = 'desc',
    ) {
    }
}
