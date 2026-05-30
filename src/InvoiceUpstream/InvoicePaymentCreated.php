<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

final readonly class InvoicePaymentCreated
{
    public function __construct(
        public int $paymentId,
        public int $invoiceId,
        public int $amountCents,
        public string $paidAt,
        public string $externalReference,
    ) {
    }
}
