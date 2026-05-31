<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use RuntimeException;

final class DunningPausedException extends RuntimeException
{
    public function __construct(public readonly int $invoiceId, public readonly string $pausedReason)
    {
        parent::__construct("Dunning is paused for invoice {$invoiceId}");
    }
}
