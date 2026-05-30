<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use RuntimeException;

final class ClientCreditNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Client credit %d was not found.', $id));
    }
}
