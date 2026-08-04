<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

/**
 * One line of a scheduled run's report: what the run decided about one invoice
 * and why (#400 §9).
 */
final readonly class ScheduledDunningDecision
{
    public function __construct(
        public int $organizationId,
        public int $invoiceId,
        public string $invoiceNumber,
        public ScheduledDunningOutcome $outcome,
        /** The stage that was (or would have been) sent; null when nothing was sent. */
        public ?DunningStage $stage = null,
        /** Free text for the run log — an exception message, a next-allowed time. */
        public ?string $detail = null,
    ) {
    }
}
