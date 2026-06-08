<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

/**
 * Server-side filter + sort for the manual-receivable list. Every field is
 * optional; null/empty means "no constraint". `sortColumn` / `sortDirection`
 * are validated against a whitelist in the repository, so they are always safe
 * to interpolate into ORDER BY.
 */
final readonly class ManualReceivableFilter
{
    public function __construct(
        public ?ManualReceivableStatus $status = null,
        /** Substring match on reference_number or client_name. */
        public ?string $q = null,
        public ?string $dueFrom = null,
        public ?string $dueTo = null,
        public string $sortColumn = 'id',
        public string $sortDirection = 'desc',
    ) {
    }

    /**
     * @param array<string, mixed> $q
     */
    public static function fromQueryParams(array $q): self
    {
        $str = static fn (string $k): ?string => isset($q[$k]) && is_string($q[$k]) && $q[$k] !== '' ? $q[$k] : null;
        $statusParam = $q['status'] ?? null;

        return new self(
            status: is_string($statusParam) ? ManualReceivableStatus::tryFrom($statusParam) : null,
            q: $str('q'),
            dueFrom: $str('due_from'),
            dueTo: $str('due_to'),
            sortColumn: $str('sort_by') ?? 'id',
            sortDirection: $str('sort_dir') ?? 'desc',
        );
    }
}
