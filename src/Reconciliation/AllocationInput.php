<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use NeneClear\Receivable\ReceivableSource;

/**
 * One allocation in a confirm request: an amount applied to a single receivable,
 * which is either an upstream invoice (Invoice is SSOR; payment written back) or
 * a manually-entered receivable (Clear is SSOR; no write-back). Build via the
 * source-specific factories so exactly one target id is ever set (ADR 0014).
 */
final readonly class AllocationInput
{
    private function __construct(
        public ReceivableSource $source,
        public ?int $invoiceId,
        public ?int $manualReceivableId,
        public int $amountCents,
    ) {
    }

    public static function upstream(int $invoiceId, int $amountCents): self
    {
        return new self(ReceivableSource::InvoiceUpstream, $invoiceId, null, $amountCents);
    }

    public static function manual(int $manualReceivableId, int $amountCents): self
    {
        return new self(ReceivableSource::Manual, null, $manualReceivableId, $amountCents);
    }

    /** The target receivable id, whichever source this allocation uses. */
    public function targetId(): int
    {
        return $this->source === ReceivableSource::Manual
            ? (int) $this->manualReceivableId
            : (int) $this->invoiceId;
    }
}
