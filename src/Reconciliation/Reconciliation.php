<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class Reconciliation
{
    public function __construct(
        public int $organizationId,
        public int $bankTransactionId,
        public ReconciliationStatus $status,
        public int $confirmedBy,
        public string $confirmedAt,
        public ?string $reasonCode = null,
        public ?string $reversedAt = null,
        public ?string $reversalReason = null,
        public ?string $idempotencyKey = null,
        public ?int $id = null,
    ) {
    }
}
