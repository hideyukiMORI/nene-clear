<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use RuntimeException;

final class ReconciliationAlreadyReversedException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Reconciliation %d is already reversed.', $id));
    }
}
