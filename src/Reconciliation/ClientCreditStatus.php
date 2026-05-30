<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

enum ClientCreditStatus: string
{
    case Open = 'open';
    case Voided = 'voided';
}
