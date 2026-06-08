<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ProposeMatchOutput
{
    /**
     * @param list<MatchSuggestion> $suggestions
     * @param bool $upstreamUnavailable true when the Invoice upstream could not be
     *             reached, so only manual candidates are returned (ADR 0014 degraded
     *             mode — the UI surfaces this rather than failing the whole request)
     */
    public function __construct(
        public int $bankTransactionId,
        public array $suggestions,
        public bool $upstreamUnavailable = false,
    ) {
    }
}
