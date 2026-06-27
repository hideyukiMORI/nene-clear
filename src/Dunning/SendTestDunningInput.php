<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

final readonly class SendTestDunningInput
{
    public function __construct(
        public int $organizationId,
        public int $invoiceId,
        public string $to,
        public int $actorUserId,
    ) {
    }
}
