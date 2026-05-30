<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use RuntimeException;

final class BankTransactionNotMatchableException extends RuntimeException
{
    public function __construct(public readonly int $bankTransactionId, public readonly string $currentStatus)
    {
        parent::__construct(sprintf(
            'Bank transaction %d cannot be matched in status "%s".',
            $bankTransactionId,
            $currentStatus,
        ));
    }
}
