<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Closure;
use DateTimeImmutable;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;
use NeneClear\ClearSettings\ClearSettingsRepositoryInterface;
use NeneClear\InvoiceUpstream\InvoiceItem;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\Scheduler\SchedulerLockInterface;
use Throwable;

/**
 * One unattended dunning sweep (#400 §4).
 *
 * This class decides **which** invoices a run offers up and in what order. It
 * decides nothing about whether an invoice may be dunned: upstream status,
 * outstanding balance, active pauses and the minimum interval are all
 * {@see SendDunningUseCase}'s to enforce, and this class learns the answer by
 * calling it and catching its exceptions. Re-checking any of them here would
 * create a second copy of a safety guard, and the unattended path would drift
 * away from the operator path the first time one copy changed.
 *
 * The two things it does own are the ones the operator path has no equivalent of:
 * the send window (§4) and the escalation ladder (§5).
 */
final readonly class SendScheduledDunningUseCase
{
    /** How long one organization's lock is held before it is considered abandoned. */
    private const int LOCK_TTL_SECONDS = 900;

    /**
     * @param Closure(DatabaseQueryExecutorInterface): ClearSettingsRepositoryInterface $clearSettings
     * @param Closure(DatabaseQueryExecutorInterface): DunningNoticeRepositoryInterface $notices
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $reader,
        private Closure $clearSettings,
        private Closure $notices,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private SendDunningUseCaseInterface $sendDunning,
        private SchedulerLockInterface $lock,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param ?int $organizationId restrict the run to one organization (the CLI's
     *                             `--organization`); null walks every opted-in one
     */
    public function execute(string $runId, bool $isDryRun = false, ?int $organizationId = null): ScheduledDunningReport
    {
        $now = $this->clock->now();
        $settingsRepo = ($this->clearSettings)($this->reader);

        $organizationIds = $settingsRepo->findOrganizationIdsWithScheduledDunning();

        if ($organizationId !== null) {
            $organizationIds = array_values(array_filter(
                $organizationIds,
                static fn (int $id): bool => $id === $organizationId,
            ));
        }

        $decisions = [];
        $skipped = [];

        foreach ($organizationIds as $id) {
            $settings = $settingsRepo->findByOrganization($id);

            if ($settings === null) {
                continue;
            }

            $policy = new DunningSchedulePolicy($settings->dunningSchedule);

            if (!$policy->isEnabled()) {
                $skipped[$id] = 'not_enabled';
                continue;
            }

            if (!$policy->isWindowOpen($now)) {
                $skipped[$id] = 'window_closed';
                continue;
            }

            // A dry run must not take the lock. It sends nothing, so it cannot
            // collide with anything — and if it did take one, running `--dry-run`
            // to inspect the queue would silently suppress the real run behind it.
            $holderToken = $runId . ':' . $id;
            $lockKey = 'dunning:' . $id;

            if (!$isDryRun && !$this->lock->acquire($lockKey, $holderToken, self::LOCK_TTL_SECONDS, $now)) {
                // An overlapping cron tick is normal operation, not an error (§8).
                $skipped[$id] = 'already_running';
                continue;
            }

            try {
                foreach ($this->decideForOrganization($id, $policy, $runId, $isDryRun, $now) as $decision) {
                    $decisions[] = $decision;
                }
            } catch (Throwable $e) {
                // One tenant's problem must not stop the others. §9 says a failing
                // candidate does not abort the run; the same has to hold a level up,
                // because the work that happens before any candidate exists — asking
                // the upstream for invoices — is exactly what an outage breaks. Left
                // unhandled, a single unreachable Invoice deployment would silently
                // stop dunning for every other organization on the host.
                $skipped[$id] = 'failed: ' . $e->getMessage();
            } finally {
                if (!$isDryRun) {
                    $this->lock->release($lockKey, $holderToken);
                }
            }
        }

        return new ScheduledDunningReport(
            runId: $runId,
            isDryRun: $isDryRun,
            decisions: $decisions,
            skippedOrganizations: $skipped,
        );
    }

    /**
     * @return list<ScheduledDunningDecision>
     */
    private function decideForOrganization(
        int $organizationId,
        DunningSchedulePolicy $policy,
        string $runId,
        bool $isDryRun,
        DateTimeImmutable $now,
    ): array {
        $invoices = $this->invoiceClient->listInvoices($organizationId, ['issued', 'partially_paid', 'overdue']);

        // Oldest due first: if the cap bites, the invoices that have been waiting
        // longest are the ones that get attention, not whichever the upstream
        // happened to list first.
        usort($invoices, static fn (InvoiceItem $a, InvoiceItem $b): int => ($a->dueAt ?? '9999-12-31') <=> ($b->dueAt ?? '9999-12-31'));

        $noticeRepo = ($this->notices)($this->reader);
        $decisions = [];
        $sent = 0;

        foreach ($invoices as $invoice) {
            $daysPastDue = $policy->daysPastDue($invoice->dueAt, $now);

            if ($daysPastDue === null) {
                $decisions[] = $this->decision($organizationId, $invoice, ScheduledDunningOutcome::NoDueDate);
                continue;
            }

            $ageStage = $policy->stageFor($daysPastDue);

            if ($ageStage === null) {
                $decisions[] = $this->decision(
                    $organizationId,
                    $invoice,
                    ScheduledDunningOutcome::BelowThreshold,
                    detail: $daysPastDue . ' day(s) past due',
                );
                continue;
            }

            // Escalation never skips a rung (§5). The ladder is capped by what has
            // actually been sent, which is why #414 had to record the stage first:
            // before that, this decision had no evidence to stand on.
            $lastNotice = $noticeRepo->findLastByInvoice($organizationId, $invoice->invoiceId);
            $reachable = DunningStage::highestReachableAfter($lastNotice?->stage);
            $stage = $ageStage->rank() <= $reachable->rank() ? $ageStage : $reachable;

            if ($policy->requiresApproval($stage)) {
                $decisions[] = $this->decision(
                    $organizationId,
                    $invoice,
                    ScheduledDunningOutcome::AwaitingApproval,
                    $stage,
                    $daysPastDue . ' day(s) past due',
                );
                continue;
            }

            if ($sent >= $policy->maxPerRun()) {
                $decisions[] = $this->decision($organizationId, $invoice, ScheduledDunningOutcome::CapReached, $stage);
                continue;
            }

            if ($isDryRun) {
                // Counted as if sent, so the cap shapes the dry run exactly as it
                // would shape the real one — a preview that ignored the cap would
                // promise sends the real run then withholds.
                ++$sent;
                $decisions[] = $this->decision($organizationId, $invoice, ScheduledDunningOutcome::Sent, $stage);
                continue;
            }

            $decisions[] = $this->send($organizationId, $invoice, $stage, $runId, $sent);
        }

        return $decisions;
    }

    private function send(int $organizationId, InvoiceItem $invoice, DunningStage $stage, string $runId, int &$sent): ScheduledDunningDecision
    {
        try {
            $this->sendDunning->execute(new SendDunningInput(
                organizationId: $organizationId,
                invoiceId: $invoice->invoiceId,
                // The unattended actor: this repo's existing "no human actor"
                // value. `trigger` (#413) is what tells it apart from a failed
                // login, which also records 0.
                actorUserId: 0,
                stage: $stage,
                trigger: DunningTrigger::Scheduled,
                dunningRunId: $runId,
            ));

            ++$sent;

            return $this->decision($organizationId, $invoice, ScheduledDunningOutcome::Sent, $stage);
        } catch (DunningPausedException $e) {
            return $this->decision($organizationId, $invoice, ScheduledDunningOutcome::Paused, $stage, $e->getMessage());
        } catch (DunningTooFrequentException $e) {
            return $this->decision($organizationId, $invoice, ScheduledDunningOutcome::TooFrequent, $stage, $e->getMessage());
        } catch (InvoiceAlreadyPaidException $e) {
            return $this->decision($organizationId, $invoice, ScheduledDunningOutcome::AlreadyPaid, $stage, $e->getMessage());
        } catch (Throwable $e) {
            // One bad candidate does not abort the sweep (§9). The failure is
            // recorded and the next invoice is evaluated; the recorded "attempted"
            // trail from SendDunningUseCase stays honest either way.
            return $this->decision($organizationId, $invoice, ScheduledDunningOutcome::Failed, $stage, $e->getMessage());
        }
    }

    private function decision(
        int $organizationId,
        InvoiceItem $invoice,
        ScheduledDunningOutcome $outcome,
        ?DunningStage $stage = null,
        ?string $detail = null,
    ): ScheduledDunningDecision {
        return new ScheduledDunningDecision(
            organizationId: $organizationId,
            invoiceId: $invoice->invoiceId,
            invoiceNumber: $invoice->invoiceNumber,
            outcome: $outcome,
            stage: $stage,
            detail: $detail,
        );
    }
}
