<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ApplyCreditOutput
{
    public function __construct(
        public ClientCredit $credit,
    ) {
    }
}
