<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

final readonly class BankTransactionFilter
{
    public function __construct(
        public ?BankTransactionStatus $status = null,
        public ?string $valueDateFrom = null,
        public ?string $valueDateTo = null,
        public ?int $amountMinCents = null,
        public ?int $amountMaxCents = null,
        public ?string $counterparty = null,
        public string $sortColumn = 'value_date',
        public string $sortDirection = 'desc',
        public bool $openForMatchingOnly = false,
    ) {
    }

    /**
     * Build from request query params (shared by the list endpoint and the CSV
     * export so both apply the same filter). `openForMatchingOnly` is not a
     * query param — the unmatched endpoint passes it explicitly.
     *
     * @param array<string, mixed> $q
     */
    public static function fromQueryParams(array $q, bool $openForMatchingOnly = false): self
    {
        $str = static fn (string $k): ?string => isset($q[$k]) && is_string($q[$k]) && $q[$k] !== '' ? $q[$k] : null;
        $int = static fn (string $k): ?int => isset($q[$k]) && is_numeric($q[$k]) ? (int) $q[$k] : null;
        $statusParam = $q['status'] ?? null;

        return new self(
            status: is_string($statusParam) ? BankTransactionStatus::tryFrom($statusParam) : null,
            valueDateFrom: $str('value_date_from'),
            valueDateTo: $str('value_date_to'),
            amountMinCents: $int('amount_min_cents'),
            amountMaxCents: $int('amount_max_cents'),
            counterparty: $str('counterparty'),
            sortColumn: $str('sort_by') ?? 'value_date',
            sortDirection: $str('sort_dir') ?? 'desc',
            openForMatchingOnly: $openForMatchingOnly,
        );
    }
}
