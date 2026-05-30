<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class MatchSuggestion
{
    public function __construct(
        public int $invoiceId,
        public string $invoiceNumber,
        public int $amountCents,
        public int $outstandingCents,
        public float $score,
        public string $reason,
    ) {
    }
}
