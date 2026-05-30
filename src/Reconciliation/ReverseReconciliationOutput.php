<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ReverseReconciliationOutput
{
    public function __construct(
        public int $reconciliationId,
    ) {
    }
}
