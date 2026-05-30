<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ReverseReconciliationInput
{
    public function __construct(
        public int $organizationId,
        public int $reconciliationId,
        public int $actorUserId,
        public string $reversalReason,
    ) {
    }
}
