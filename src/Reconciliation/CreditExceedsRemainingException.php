<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use RuntimeException;

final class CreditExceedsRemainingException extends RuntimeException
{
    public function __construct(public readonly int $remainingCents)
    {
        parent::__construct(sprintf('Amount exceeds remaining credit balance of %d cents.', $remainingCents));
    }
}
