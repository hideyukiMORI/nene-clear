<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ProposeMatchInput
{
    public function __construct(
        public int $organizationId,
        public int $bankTransactionId,
    ) {
    }
}
