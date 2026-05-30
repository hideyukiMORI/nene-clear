<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ConfirmMatchOutput
{
    public function __construct(
        public int $reconciliationId,
    ) {
    }
}
