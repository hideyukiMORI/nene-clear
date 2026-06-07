<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

/**
 * Server-side filter + sort for the dunning-notice history list (Issue #130).
 * Every field is optional; null/empty means "no constraint". `sortColumn` is
 * validated against a whitelist in the repository.
 *
 * `sentFrom` / `sentTo` are inclusive `YYYY-MM-DD` bounds on `DATE(sent_at)`.
 */
final readonly class DunningNoticeFilter
{
    public function __construct(
        public ?string $invoiceNumber = null,
        public ?string $recipientEmail = null,
        public ?int $outstandingMinCents = null,
        public ?int $outstandingMaxCents = null,
        public ?string $sentFrom = null,
        public ?string $sentTo = null,
        public ?int $sentBy = null,
        public string $sortColumn = 'sent_at',
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

        return new self(
            invoiceNumber: $str('invoice_number'),
            recipientEmail: $str('recipient_email'),
            outstandingMinCents: $int('outstanding_min_cents'),
            outstandingMaxCents: $int('outstanding_max_cents'),
            sentFrom: $str('sent_from'),
            sentTo: $str('sent_to'),
            sentBy: $int('sent_by'),
            sortColumn: $str('sort_by') ?? 'sent_at',
            sortDirection: $str('sort_dir') ?? 'desc',
        );
    }
}
