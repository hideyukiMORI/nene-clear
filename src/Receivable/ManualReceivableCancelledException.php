<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use RuntimeException;

/** Raised when editing or cancelling a receivable that is already cancelled. */
final class ManualReceivableCancelledException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Manual receivable %d is cancelled.', $id));
    }
}
