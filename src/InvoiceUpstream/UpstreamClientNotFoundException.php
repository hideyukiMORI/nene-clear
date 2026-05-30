<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

use RuntimeException;

final class UpstreamClientNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $clientId)
    {
        parent::__construct(sprintf('Client %d was not found in the upstream system.', $clientId));
    }
}
