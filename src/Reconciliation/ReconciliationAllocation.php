<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use NeneClear\Receivable\ReceivableSource;

final readonly class ReconciliationAllocation
{
    public function __construct(
        public int $organizationId,
        public int $reconciliationId,
        /** Set for `invoice_upstream`; null for `manual` (see manualReceivableId). */
        public ?int $invoiceId,
        public int $amountCents,
        public ?int $paymentId = null,
        public ?string $externalReference = null,
        public ?int $id = null,
        public ReceivableSource $source = ReceivableSource::InvoiceUpstream,
        /** Set for `manual`; null for `invoice_upstream`. */
        public ?int $manualReceivableId = null,
    ) {
    }
}
