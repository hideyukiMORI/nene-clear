<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

final readonly class UpdateManualReceivableInput
{
    public function __construct(
        public int $id,
        public int $callerOrganizationId,
        public string $referenceNumber,
        public string $clientName,
        public ?string $recipientEmail,
        public int $totalCents,
        public string $currency,
        public ?string $issuedAt,
        public ?string $dueAt,
        public int $actorUserId,
    ) {
    }
}
