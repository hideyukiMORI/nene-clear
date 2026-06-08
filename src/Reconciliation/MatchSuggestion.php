<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use NeneClear\Receivable\ReceivableSource;

/**
 * One ranked candidate a deposit could be reconciled against. The target is
 * either an upstream invoice or a manually-entered receivable (ADR 0014),
 * distinguished by `source`. Build via the source-specific factories so exactly
 * one target id is ever set.
 */
final readonly class MatchSuggestion
{
    private function __construct(
        public ReceivableSource $source,
        public ?int $invoiceId,
        public ?string $invoiceNumber,
        public ?int $manualReceivableId,
        public ?string $referenceNumber,
        public int $amountCents,
        public int $outstandingCents,
        public float $score,
        public string $reason,
    ) {
    }

    public static function upstream(
        int $invoiceId,
        string $invoiceNumber,
        int $amountCents,
        int $outstandingCents,
        float $score,
        string $reason,
    ): self {
        return new self(ReceivableSource::InvoiceUpstream, $invoiceId, $invoiceNumber, null, null, $amountCents, $outstandingCents, $score, $reason);
    }

    public static function manual(
        int $manualReceivableId,
        string $referenceNumber,
        int $amountCents,
        int $outstandingCents,
        float $score,
        string $reason,
    ): self {
        return new self(ReceivableSource::Manual, null, null, $manualReceivableId, $referenceNumber, $amountCents, $outstandingCents, $score, $reason);
    }
}
