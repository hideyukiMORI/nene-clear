<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

enum AccountType: string
{
    case Ordinary = 'ordinary';
    case Current = 'current';
}
