<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ClientCredit
{
    public function __construct(
        public int $organizationId,
        public int $clientId,
        public int $amountCents,
        public int $remainingCents,
        public ClientCreditStatus $status,
        public int $sourceBankTransactionId,
        public int $reconciliationId,
        public int $createdBy,
        public string $createdAt,
        public ?int $id = null,
    ) {
    }
}
