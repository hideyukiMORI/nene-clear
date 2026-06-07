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

    /**
     * Build from request query params, shared by the list endpoint and the CSV
     * export so both apply the same filter.
     *
     * @param array<string, mixed> $q
     */
    public static function fromQueryParams(array $q): self
    {
        $str = static fn (string $k): ?string => isset($q[$k]) && is_string($q[$k]) && $q[$k] !== '' ? $q[$k] : null;
        $int = static fn (string $k): ?int => isset($q[$k]) && is_numeric($q[$k]) ? (int) $q[$k] : null;
        $statusParam = $q['status'] ?? null;

        return new self(
            status: is_string($statusParam) ? ReconciliationStatus::tryFrom($statusParam) : null,
            bankTransactionId: $int('bank_transaction_id'),
            invoiceId: $int('invoice_id'),
            confirmedFrom: $str('confirmed_from'),
            confirmedTo: $str('confirmed_to'),
            sortColumn: $str('sort_by') ?? 'id',
            sortDirection: $str('sort_dir') ?? 'desc',
        );
    }
}
