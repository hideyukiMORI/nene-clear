<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use RuntimeException;

final class InvoiceAlreadyPaidException extends RuntimeException
{
    public function __construct(public readonly int $invoiceId, public readonly string $status)
    {
        parent::__construct(sprintf('Invoice %d is not eligible for dunning (status: %s).', $invoiceId, $status));
    }
}
