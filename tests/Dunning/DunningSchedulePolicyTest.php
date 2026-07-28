<?php

declare(strict_types=1);

namespace NeneClear\Tests\Dunning;

use DateTimeImmutable;
use NeneClear\ClearSettings\DunningSchedule;
use NeneClear\Dunning\DunningSchedulePolicy;
use NeneClear\Dunning\DunningStage;
use PHPUnit\Framework\TestCase;

final class DunningSchedulePolicyTest extends TestCase
{
    private function policy(
        int $startHour = 9,
        int $endHour = 18,
        bool $weekdaysOnly = true,
        int $initial = 3,
        int $reminder = 14,
        int $final = 30,
    ): DunningSchedulePolicy {
        return new DunningSchedulePolicy(new DunningSchedule(
            isEnabled: true,
            initialAfterDays: $initial,
            reminderAfterDays: $reminder,
            finalAfterDays: $final,
            windowStartHour: $startHour,
            windowEndHour: $endHour,
            isWeekdaysOnly: $weekdaysOnly,
        ));
    }

    /** 2026-07-29 is a Wednesday; 2026-08-01 a Saturday, 2026-08-02 a Sunday. */
    public function testWindowIsOpenInsideBusinessHoursOnAWeekday(): void
    {
        self::assertTrue($this->policy()->isWindowOpen(new DateTimeImmutable('2026-07-29 09:00:00')));
        self::assertTrue($this->policy()->isWindowOpen(new DateTimeImmutable('2026-07-29 17:59:59')));
    }

    public function testWindowIsClosedBeforeStartAndFromEndHourOnward(): void
    {
        self::assertFalse($this->policy()->isWindowOpen(new DateTimeImmutable('2026-07-29 08:59:59')));
        // 18:00 is outside: the window is [start, end).
        self::assertFalse($this->policy()->isWindowOpen(new DateTimeImmutable('2026-07-29 18:00:00')));
        self::assertFalse($this->policy()->isWindowOpen(new DateTimeImmutable('2026-07-29 03:00:00')));
    }

    public function testWindowIsClosedAtTheWeekendWhenWeekdaysOnly(): void
    {
        self::assertFalse($this->policy()->isWindowOpen(new DateTimeImmutable('2026-08-01 10:00:00')));
        self::assertFalse($this->policy()->isWindowOpen(new DateTimeImmutable('2026-08-02 10:00:00')));
    }

    public function testWeekendIsAllowedWhenWeekdaysOnlyIsOff(): void
    {
        $policy = $this->policy(weekdaysOnly: false);

        self::assertTrue($policy->isWindowOpen(new DateTimeImmutable('2026-08-01 10:00:00')));
    }

    /**
     * A wrapping window (22:00–06:00) is treated as closed rather than guessed
     * at. Sending overnight because the settings were nonsense is worse than
     * sending nothing.
     */
    public function testWrappingWindowIsTreatedAsClosed(): void
    {
        $policy = $this->policy(startHour: 22, endHour: 6);

        self::assertFalse($policy->isWindowOpen(new DateTimeImmutable('2026-07-29 23:00:00')));
        self::assertFalse($policy->isWindowOpen(new DateTimeImmutable('2026-07-29 05:00:00')));
        self::assertFalse($policy->isWindowOpen(new DateTimeImmutable('2026-07-29 12:00:00')));
    }

    public function testStageThresholdsEscalateOnTheirExactBoundaries(): void
    {
        $policy = $this->policy();

        self::assertNull($policy->stageFor(0));
        self::assertNull($policy->stageFor(2));
        self::assertSame(DunningStage::Initial, $policy->stageFor(3));
        self::assertSame(DunningStage::Initial, $policy->stageFor(13));
        self::assertSame(DunningStage::Reminder, $policy->stageFor(14));
        self::assertSame(DunningStage::Reminder, $policy->stageFor(29));
        self::assertSame(DunningStage::Final, $policy->stageFor(30));
        self::assertSame(DunningStage::Final, $policy->stageFor(365));
    }

    public function testInvoiceNotYetDueHasNoStage(): void
    {
        self::assertNull($this->policy()->stageFor(-5));
    }

    /**
     * Thresholds are per-organization settings, so they can be saved in a
     * non-ascending order. Escalation must stay monotonic instead of picking a
     * stage at random.
     */
    public function testNonAscendingThresholdsStillEscalateMonotonically(): void
    {
        $policy = $this->policy(initial: 30, reminder: 14, final: 3);

        self::assertSame(DunningStage::Final, $policy->stageFor(3));
        self::assertSame(DunningStage::Final, $policy->stageFor(40));
    }

    public function testDaysPastDueCountsWholeCalendarDays(): void
    {
        $policy = $this->policy();
        $now = new DateTimeImmutable('2026-07-29 10:00:00');

        self::assertSame(0, $policy->daysPastDue('2026-07-29', $now));
        self::assertSame(1, $policy->daysPastDue('2026-07-28', $now));
        self::assertSame(30, $policy->daysPastDue('2026-06-29', $now));
        self::assertSame(-2, $policy->daysPastDue('2026-07-31', $now));
    }

    public function testDaysPastDueIgnoresTheTimeOfDay(): void
    {
        $policy = $this->policy();

        // Due late on the 28th, now early on the 29th: one calendar day, not zero.
        self::assertSame(
            1,
            $policy->daysPastDue('2026-07-28 23:59:59', new DateTimeImmutable('2026-07-29 00:01:00')),
        );
    }

    /**
     * `InvoiceItem::$dueAt` is nullable by the Invoice contract. "Days past due"
     * is undefined without a due date, so such invoices are never scheduled
     * candidates — an operator can still dun them by hand.
     */
    public function testInvoiceWithoutADueDateHasNoDaysPastDue(): void
    {
        $policy = $this->policy();
        $now = new DateTimeImmutable('2026-07-29 10:00:00');

        self::assertNull($policy->daysPastDue(null, $now));
        self::assertNull($policy->daysPastDue('', $now));
        self::assertNull($policy->daysPastDue('   ', $now));
        self::assertNull($policy->daysPastDue('not-a-date', $now));
    }

    public function testFinalStageRequiresApprovalAndOthersDoNot(): void
    {
        $policy = $this->policy();

        self::assertTrue($policy->requiresApproval(DunningStage::Final));
        self::assertFalse($policy->requiresApproval(DunningStage::Initial));
        self::assertFalse($policy->requiresApproval(DunningStage::Reminder));
    }

    public function testDefaultsAreDisabledAndMatchTheMigration(): void
    {
        $schedule = new DunningSchedule();
        $policy = new DunningSchedulePolicy($schedule);

        self::assertFalse($policy->isEnabled());
        self::assertSame(50, $policy->maxPerRun());
        self::assertSame(3, $schedule->initialAfterDays);
        self::assertSame(14, $schedule->reminderAfterDays);
        self::assertSame(30, $schedule->finalAfterDays);
        self::assertSame(9, $schedule->windowStartHour);
        self::assertSame(18, $schedule->windowEndHour);
        self::assertTrue($schedule->isWeekdaysOnly);
    }
}
