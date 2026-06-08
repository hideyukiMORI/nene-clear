<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

final readonly class InvoiceItem
{
    public function __construct(
        public int $invoiceId,
        public string $invoiceNumber,
        public int $clientId,
        public int $outstandingCents,
        public int $totalCents,
        /** Nullable per the Invoice contract — an invoice may be issued with no due date. */
        public ?string $dueAt,
        public string $status,
        public string $currency,
    ) {
    }
}
