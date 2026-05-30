<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

enum ReconciliationStatus: string
{
    case Confirmed = 'confirmed';
    case Reversed = 'reversed';
}
