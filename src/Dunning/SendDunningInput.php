<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

final readonly class SendDunningInput
{
    public function __construct(
        public int $organizationId,
        public int $invoiceId,
        public int $actorUserId,
        public DunningStage $stage = DunningStage::Initial,
    ) {
    }
}
