<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class AllocationInput
{
    public function __construct(
        public int $invoiceId,
        public int $amountCents,
    ) {
    }
}
