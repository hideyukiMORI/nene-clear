<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use NeneClear\Receivable\ReceivableSource;

final readonly class ClientCredit
{
    public function __construct(
        public int $organizationId,
        /** Upstream client id (`invoice_upstream`); null for `manual` (payer is in clientName). */
        public ?int $clientId,
        public int $amountCents,
        public int $remainingCents,
        public ClientCreditStatus $status,
        public int $sourceBankTransactionId,
        public int $reconciliationId,
        public int $createdBy,
        public string $createdAt,
        public ?int $id = null,
        public ReceivableSource $source = ReceivableSource::InvoiceUpstream,
        public ?int $manualReceivableId = null,
        public ?string $clientName = null,
    ) {
    }
}
