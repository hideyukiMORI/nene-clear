<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use RuntimeException;

final class BankImportBatchHasMatchedLinesException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Bank import batch %d has matched or partially matched lines.', $id));
    }
}
