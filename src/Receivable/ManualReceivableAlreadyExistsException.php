<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use RuntimeException;

/** Raised when a non-deleted receivable already uses the same reference_number in the tenant. */
final class ManualReceivableAlreadyExistsException extends RuntimeException
{
    public function __construct(public readonly string $referenceNumber)
    {
        parent::__construct(sprintf('Manual receivable with reference "%s" already exists.', $referenceNumber));
    }
}
