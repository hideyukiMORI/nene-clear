<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use RuntimeException;

final class ManualReceivableNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Manual receivable %d was not found.', $id));
    }
}
