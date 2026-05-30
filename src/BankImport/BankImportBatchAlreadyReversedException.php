<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use RuntimeException;

final class BankImportBatchAlreadyReversedException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Bank import batch %d is already reversed.', $id));
    }
}
