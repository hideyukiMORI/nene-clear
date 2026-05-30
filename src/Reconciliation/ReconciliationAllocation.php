<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ReconciliationAllocation
{
    public function __construct(
        public int $organizationId,
        public int $reconciliationId,
        public int $invoiceId,
        public int $amountCents,
        public ?int $paymentId = null,
        public ?string $externalReference = null,
        public ?int $id = null,
    ) {
    }
}
