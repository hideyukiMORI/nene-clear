<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

/**
 * The result of one scheduled-dunning run (#400 §9).
 *
 * `$skippedOrganizations` records organizations the run did not walk at all —
 * outside their send window, or already being processed by an overlapping tick.
 * An empty report with no explanation is the failure mode this exists to prevent:
 * an operator who enabled the feature and saw nothing happen must be able to tell
 * "nothing was due" from "the window was closed".
 */
final readonly class ScheduledDunningReport
{
    /**
     * @param list<ScheduledDunningDecision> $decisions
     * @param array<int, string>             $skippedOrganizations organization id => reason
     */
    public function __construct(
        public string $runId,
        public bool $isDryRun,
        public array $decisions = [],
        public array $skippedOrganizations = [],
    ) {
    }

    public function sentCount(): int
    {
        return count(array_filter(
            $this->decisions,
            static fn (ScheduledDunningDecision $d): bool => $d->outcome === ScheduledDunningOutcome::Sent,
        ));
    }

    public function candidateCount(): int
    {
        return count($this->decisions);
    }

    /** @return list<ScheduledDunningDecision> */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->decisions,
            static fn (ScheduledDunningDecision $d): bool => $d->outcome === ScheduledDunningOutcome::Failed,
        ));
    }
}
