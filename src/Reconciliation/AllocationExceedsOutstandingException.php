<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use RuntimeException;

final class AllocationExceedsOutstandingException extends RuntimeException
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $outstandingCents,
    ) {
        parent::__construct(sprintf(
            'Allocation for invoice %d exceeds outstanding amount of %d cents.',
            $invoiceId,
            $outstandingCents,
        ));
    }
}
