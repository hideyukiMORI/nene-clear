<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

/**
 * Server-side filter + sort for the client-credit list (Issue #130).
 *
 * Every field is optional; null/empty means "no constraint". `sortColumn` is
 * validated against a whitelist in the repository, so it is always safe to
 * interpolate into ORDER BY.
 */
final readonly class ClientCreditFilter
{
    public function __construct(
        public ?int $clientId = null,
        public ?ClientCreditStatus $status = null,
        public ?int $amountMinCents = null,
        public ?int $amountMaxCents = null,
        public ?int $remainingMinCents = null,
        public ?int $remainingMaxCents = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
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
            clientId: $int('client_id'),
            status: is_string($statusParam) ? ClientCreditStatus::tryFrom($statusParam) : null,
            amountMinCents: $int('amount_min_cents'),
            amountMaxCents: $int('amount_max_cents'),
            remainingMinCents: $int('remaining_min_cents'),
            remainingMaxCents: $int('remaining_max_cents'),
            createdFrom: $str('created_from'),
            createdTo: $str('created_to'),
            sortColumn: $str('sort_by') ?? 'id',
            sortDirection: $str('sort_dir') ?? 'desc',
        );
    }
}
