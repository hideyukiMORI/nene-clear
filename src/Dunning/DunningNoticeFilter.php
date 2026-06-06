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
}
