<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ProposeMatchOutput
{
    /**
     * @param list<MatchSuggestion> $suggestions
     */
    public function __construct(
        public int $bankTransactionId,
        public array $suggestions,
    ) {
    }
}
