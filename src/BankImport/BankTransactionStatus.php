<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

enum BankTransactionStatus: string
{
    case Unmatched = 'unmatched';
    case PartiallyMatched = 'partially_matched';
    case Matched = 'matched';
    case Voided = 'voided';
}
