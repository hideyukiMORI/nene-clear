<?php

declare(strict_types=1);

namespace NeneClear\Tests\Dunning;

use DateTimeImmutable;
use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneClear\ClearSettings\ClearSettings;
use NeneClear\ClearSettings\DunningSchedule;
use NeneClear\Dunning\DunningNotice;
use NeneClear\Dunning\DunningStage;
use NeneClear\Dunning\ScheduledDunningOutcome;
use NeneClear\Dunning\SendDunningInput;
use NeneClear\Dunning\SendDunningOutput;
use NeneClear\Dunning\SendDunningUseCaseInterface;
use NeneClear\Dunning\SendScheduledDunningUseCase;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceItem;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\Scheduler\SchedulerLockInterface;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\Tests\Support\NullQueryExecutor;
use PHPUnit\Framework\TestCase;

final class SendScheduledDunningUseCaseTest extends TestCase
{
    private const int ORG = 7;

    private InMemoryClearSettingsRepository $settings;
    private InMemoryDunningNoticeRepository $notices;
    private FakeInvoiceUpstreamClient $fake;
    private InvoiceUpstreamClientInterface $upstream;
    private RecordingSendDunning $sender;
    private FakeSchedulerLock $lock;

    protected function setUp(): void
    {
        $this->settings = new InMemoryClearSettingsRepository();
        $this->notices = new InMemoryDunningNoticeRepository();
        $this->fake = new FakeInvoiceUpstreamClient();
        $this->upstream = $this->fake;
        $this->sender = new RecordingSendDunning();
        $this->lock = new FakeSchedulerLock();
    }

    private function enable(DunningSchedule $schedule = new DunningSchedule(isEnabled: true)): void
    {
        $this->settings->save(new ClearSettings(
            organizationId: self::ORG,
            upstreamBaseUrl: 'https://invoice.example',
            upstreamTokenRef: 'ref',
            dunningMinIntervalDays: 7,
            dunningSchedule: $schedule,
        ));
    }

    private function addInvoice(int $id, ?string $dueAt, int $outstanding = 110000): void
    {
        $this->fake->addInvoice(new InvoiceItem(
            invoiceId: $id,
            invoiceNumber: 'INV-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            clientId: 45,
            outstandingCents: $outstanding,
            totalCents: $outstanding,
            dueAt: $dueAt,
            status: 'overdue',
            currency: 'JPY',
        ));
    }

    /** `$now` defaults to a Wednesday at 10:00 — inside the default Mon–Fri 09–18 window. */
    private function sweep(string $now = '2026-06-10T10:00:00+00:00', bool $dryRun = false, ?int $organizationId = null): \NeneClear\Dunning\ScheduledDunningReport
    {
        $useCase = new SendScheduledDunningUseCase(
            reader: new NullQueryExecutor(),
            clearSettings: fn (DatabaseQueryExecutorInterface $ex): InMemoryClearSettingsRepository => $this->settings,
            notices: fn (DatabaseQueryExecutorInterface $ex): InMemoryDunningNoticeRepository => $this->notices,
            invoiceClient: $this->upstream,
            sendDunning: $this->sender,
            lock: $this->lock,
            clock: new FixedClock($now),
        );

        return $useCase->execute('run-1', $dryRun, $organizationId);
    }

    public function test_an_organization_that_never_opted_in_is_not_walked_at_all(): void
    {
        // Default-off is the whole safety story of #400 §6: shipping this feature
        // must not change what any existing deployment does.
        $this->addInvoice(1, '2026-06-01');

        $report = $this->sweep();

        self::assertSame(0, $report->candidateCount());
        self::assertSame([], $this->sender->sent);
    }

    public function test_outside_the_send_window_nothing_is_sent_and_the_reason_is_reported(): void
    {
        $this->enable();
        $this->addInvoice(1, '2026-06-01');

        // Sunday. A polite dunning email at the weekend still reads as harassment.
        $report = $this->sweep('2026-06-14T10:00:00+00:00');

        self::assertSame([], $this->sender->sent);

        // Reported, not silently empty: an operator who enabled the feature must be
        // able to tell "nothing was due" from "the window was closed".
        self::assertSame(['window_closed'], array_values($report->skippedOrganizations));
    }

    public function test_below_the_first_threshold_nothing_is_sent(): void
    {
        $this->enable();
        $this->addInvoice(1, '2026-06-09'); // 1 day past due; initial needs 3

        $report = $this->sweep();

        self::assertSame([], $this->sender->sent);
        self::assertSame(ScheduledDunningOutcome::BelowThreshold, $report->decisions[0]->outcome);
    }

    public function test_a_final_threshold_invoice_is_held_for_approval_not_sent(): void
    {
        $this->enable();
        // 40 days past due — past the `final` threshold of 30. It has already had
        // an initial and a reminder, so the ladder does not hold it back.
        $this->addInvoice(1, '2026-05-01');
        $this->seedNotice(1, DunningStage::Reminder);

        $report = $this->sweep();

        self::assertSame([], $this->sender->sent, 'a final demand is never sent unattended');
        self::assertSame(ScheduledDunningOutcome::AwaitingApproval, $report->decisions[0]->outcome);
        self::assertSame(DunningStage::Final, $report->decisions[0]->stage);
    }

    public function test_escalation_never_skips_a_rung(): void
    {
        $this->enable();
        // 40 days past due, so by age alone this is `final` — but nothing has been
        // sent yet, so the run may only reach `initial`. An invoice left unattended
        // must not be opened with the last message before the relationship changes.
        $this->addInvoice(1, '2026-05-01');

        $this->sweep();

        self::assertCount(1, $this->sender->sent);
        self::assertSame(DunningStage::Initial, $this->sender->sent[0]->stage);
    }

    public function test_the_rung_after_initial_is_reminder_not_final(): void
    {
        $this->enable();
        $this->addInvoice(1, '2026-05-01');
        $this->seedNotice(1, DunningStage::Initial);

        $this->sweep();

        self::assertCount(1, $this->sender->sent);
        self::assertSame(DunningStage::Reminder, $this->sender->sent[0]->stage);
    }

    public function test_a_scheduled_send_is_attributed_to_the_run_and_to_no_human(): void
    {
        $this->enable();
        $this->addInvoice(1, '2026-06-01');

        $this->sweep();

        $input = $this->sender->sent[0];
        self::assertSame(0, $input->actorUserId);
        self::assertSame('scheduled', $input->trigger->value);
        self::assertSame('run-1', $input->dunningRunId);
    }

    public function test_the_per_run_cap_bounds_the_blast_radius(): void
    {
        $this->enable(new DunningSchedule(isEnabled: true, maxPerRun: 2));
        $this->addInvoice(1, '2026-06-01');
        $this->addInvoice(2, '2026-06-02');
        $this->addInvoice(3, '2026-06-03');

        $report = $this->sweep();

        self::assertCount(2, $this->sender->sent);
        self::assertSame(ScheduledDunningOutcome::CapReached, $report->decisions[2]->outcome);
    }

    public function test_the_oldest_due_invoice_is_attended_to_first(): void
    {
        $this->enable(new DunningSchedule(isEnabled: true, maxPerRun: 1));
        $this->addInvoice(1, '2026-06-03');
        $this->addInvoice(2, '2026-05-20'); // oldest
        $this->addInvoice(3, '2026-06-01');

        $this->sweep();

        // If the cap bites, the invoice that has been waiting longest is the one
        // that gets attention — not whichever the upstream happened to list first.
        self::assertCount(1, $this->sender->sent);
        self::assertSame(2, $this->sender->sent[0]->invoiceId);
    }

    public function test_a_dry_run_sends_nothing_takes_no_lock_and_still_reports_what_would_go(): void
    {
        $this->enable();
        $this->addInvoice(1, '2026-06-01');

        $report = $this->sweep(dryRun: true);

        self::assertSame([], $this->sender->sent);
        self::assertTrue($report->isDryRun);
        self::assertSame(1, $report->sentCount());
        self::assertSame(DunningStage::Initial, $report->decisions[0]->stage);

        // A dry run that took the lock would silently suppress the real run behind it.
        self::assertSame([], $this->lock->acquired);
    }

    public function test_a_dry_run_respects_the_cap_so_it_does_not_promise_more_than_the_real_run(): void
    {
        $this->enable(new DunningSchedule(isEnabled: true, maxPerRun: 1));
        $this->addInvoice(1, '2026-06-01');
        $this->addInvoice(2, '2026-06-02');

        $report = $this->sweep(dryRun: true);

        self::assertSame(1, $report->sentCount());
        self::assertSame(ScheduledDunningOutcome::CapReached, $report->decisions[1]->outcome);
    }

    public function test_a_second_overlapping_run_takes_no_lock_and_sends_nothing(): void
    {
        $this->enable();
        $this->addInvoice(1, '2026-06-01');
        $this->lock->refuse = true;

        $report = $this->sweep();

        self::assertSame([], $this->sender->sent);
        // An overlapping cron tick is normal operation, not an error (§8).
        self::assertSame(['already_running'], array_values($report->skippedOrganizations));
    }

    public function test_the_lock_is_released_even_when_a_candidate_blows_up(): void
    {
        $this->enable();
        $this->addInvoice(1, '2026-06-01');
        $this->sender->throw = new \RuntimeException('smtp exploded');

        $report = $this->sweep();

        self::assertSame(ScheduledDunningOutcome::Failed, $report->decisions[0]->outcome);
        self::assertSame(['dunning:7'], $this->lock->released);
    }

    public function test_one_failing_candidate_does_not_abort_the_sweep(): void
    {
        $this->enable();
        $this->addInvoice(1, '2026-06-01');
        $this->addInvoice(2, '2026-06-02');
        $this->sender->throwOnInvoiceId = 1;

        $report = $this->sweep();

        self::assertSame(ScheduledDunningOutcome::Failed, $report->decisions[0]->outcome);
        self::assertSame(ScheduledDunningOutcome::Sent, $report->decisions[1]->outcome);
        self::assertCount(1, $report->failures());
    }

    public function test_an_invoice_without_a_due_date_is_never_a_scheduled_candidate(): void
    {
        $this->enable();
        $this->addInvoice(1, dueAt: '');

        $report = $this->sweep();

        // "Days past due" is undefined without one. An operator can still dun it by hand.
        self::assertSame([], $this->sender->sent);
        self::assertSame(ScheduledDunningOutcome::NoDueDate, $report->decisions[0]->outcome);
    }

    public function test_one_organizations_upstream_outage_does_not_stop_the_others(): void
    {
        $this->enable();
        $this->settings->save(new ClearSettings(
            organizationId: 99,
            upstreamBaseUrl: 'https://invoice.example',
            upstreamTokenRef: 'ref',
            dunningMinIntervalDays: 7,
            dunningSchedule: new DunningSchedule(isEnabled: true),
        ));
        $this->addInvoice(1, '2026-06-01');

        // Org 99 blows up on the listing call — the work that happens *before* any
        // candidate exists, which is exactly what an upstream outage breaks.
        $this->upstream = new FailsForOneOrgUpstream($this->upstream, failForOrganizationId: 99);

        $report = $this->sweep();

        // Org 7 still got its send. Without the per-organization catch, a single
        // unreachable Invoice deployment would silently stop dunning for everyone.
        self::assertCount(1, $this->sender->sent);
        self::assertSame(self::ORG, $this->sender->sent[0]->organizationId);
        self::assertArrayHasKey(99, $report->skippedOrganizations);
        self::assertStringStartsWith('failed:', $report->skippedOrganizations[99]);
    }

    private function seedNotice(int $invoiceId, DunningStage $stage): void
    {
        $this->notices->save(new DunningNotice(
            organizationId: self::ORG,
            invoiceId: $invoiceId,
            invoiceNumber: 'INV-001',
            clientId: 45,
            recipientEmail: 'a@acme.example',
            outstandingCents: 110000,
            dueAt: '2026-05-01',
            channel: 'log',
            templateVersion: '1.0',
            stage: $stage,
            sentBy: 42,
            sentAt: '2026-05-20 09:00:00',
        ));
    }
}

final class RecordingSendDunning implements SendDunningUseCaseInterface
{
    /** @var list<SendDunningInput> */
    public array $sent = [];

    public ?\Throwable $throw = null;

    public ?int $throwOnInvoiceId = null;

    private int $nextId = 1;

    public function execute(SendDunningInput $input): SendDunningOutput
    {
        if ($this->throw !== null || $this->throwOnInvoiceId === $input->invoiceId) {
            throw $this->throw ?? new \RuntimeException('boom');
        }

        $this->sent[] = $input;

        return new SendDunningOutput(dunningNoticeId: $this->nextId++);
    }
}

final class FakeSchedulerLock implements SchedulerLockInterface
{
    public bool $refuse = false;

    /** @var list<string> */
    public array $acquired = [];

    /** @var list<string> */
    public array $released = [];

    public function acquire(string $key, string $holderToken, int $ttlSeconds, DateTimeImmutable $now): bool
    {
        if ($this->refuse) {
            return false;
        }

        $this->acquired[] = $key;

        return true;
    }

    public function release(string $key, string $holderToken): void
    {
        $this->released[] = $key;
    }
}

/**
 * Delegates everything, except that one organization's listing call blows up —
 * the shape of an Invoice deployment being unreachable for a single tenant.
 */
final class FailsForOneOrgUpstream implements InvoiceUpstreamClientInterface
{
    public function __construct(
        private readonly InvoiceUpstreamClientInterface $inner,
        private readonly int $failForOrganizationId,
    ) {
    }

    public function listInvoices(int $organizationId, array $statuses): array
    {
        if ($organizationId === $this->failForOrganizationId) {
            throw new \RuntimeException('upstream unreachable');
        }

        return $this->inner->listInvoices($organizationId, $statuses);
    }

    public function getInvoice(int $organizationId, int $invoiceId): \NeneClear\InvoiceUpstream\InvoiceItem
    {
        return $this->inner->getInvoice($organizationId, $invoiceId);
    }

    public function createPayment(
        int $organizationId,
        int $invoiceId,
        int $amountCents,
        string $paidAt,
        string $externalReference,
        string $idempotencyKey,
    ): \NeneClear\InvoiceUpstream\InvoicePaymentCreated {
        return $this->inner->createPayment($organizationId, $invoiceId, $amountCents, $paidAt, $externalReference, $idempotencyKey);
    }

    public function voidPayment(
        int $organizationId,
        int $invoiceId,
        int $paymentId,
        string $reason,
        string $idempotencyKey,
    ): void {
        $this->inner->voidPayment($organizationId, $invoiceId, $paymentId, $reason, $idempotencyKey);
    }

    public function getClient(int $organizationId, int $clientId): \NeneClear\InvoiceUpstream\InvoiceClientInfo
    {
        return $this->inner->getClient($organizationId, $clientId);
    }
}
