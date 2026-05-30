<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

use RuntimeException;

final class UpstreamInvoiceUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'The Invoice upstream is currently unavailable.')
    {
        parent::__construct($message);
    }
}
