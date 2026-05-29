<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

enum BankImportBatchStatus: string
{
    case Imported = 'imported';
    case Reversed = 'reversed';
}
