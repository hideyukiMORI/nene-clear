<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use RuntimeException;

final class BankAccountNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Bank account %d was not found.', $id));
    }
}
