<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

final readonly class SendDunningInput
{
    /**
     * @param DunningTrigger $trigger      what initiated this send (#400 §7); recorded in the
     *                                     audit event's `metadata`. Defaults to `manual` so the
     *                                     operator path needs no change at its call sites.
     * @param ?string        $dunningRunId identifies the scheduler run that produced this send,
     *                                     so every notice from one sweep can be found together.
     *                                     Null on the manual path, where there is no run.
     */
    public function __construct(
        public int $organizationId,
        public int $invoiceId,
        public int $actorUserId,
        public DunningStage $stage = DunningStage::Initial,
        public DunningTrigger $trigger = DunningTrigger::Manual,
        public ?string $dunningRunId = null,
    ) {
    }
}
