<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use RuntimeException;

final class BankTransactionNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Bank transaction %d was not found.', $id));
    }
}
