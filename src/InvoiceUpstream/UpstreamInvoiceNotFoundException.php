<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

use RuntimeException;

final class UpstreamInvoiceNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $invoiceId)
    {
        parent::__construct(sprintf('Invoice %d was not found in the upstream system.', $invoiceId));
    }
}
