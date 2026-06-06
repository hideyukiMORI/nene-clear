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
}
