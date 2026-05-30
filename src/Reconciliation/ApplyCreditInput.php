<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ApplyCreditInput
{
    public function __construct(
        public int $organizationId,
        public int $creditId,
        public int $invoiceId,
        public int $amountCents,
        public int $actorUserId,
    ) {
    }
}
