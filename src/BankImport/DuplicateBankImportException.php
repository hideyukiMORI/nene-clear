<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use RuntimeException;

final class DuplicateBankImportException extends RuntimeException
{
    public function __construct(public readonly string $fileHash)
    {
        parent::__construct('A batch with this file hash was already imported.');
    }
}
