<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use DateTimeImmutable;
use NeneClear\ClearSettings\DunningSchedule;

/**
 * The *timing* decisions of scheduled dunning (#400): may a run send right now,
 * and which escalation stage does an invoice's age call for.
 *
 * This class decides nothing about eligibility. Whether a given invoice may be
 * dunned at all — upstream status and outstanding balance, an active pause, the
 * minimum interval since the last notice — is decided by
 * {@see SendDunningUseCase}, and the scheduler learns the answer by calling it
 * and catching its exceptions. Duplicating any of those checks here would create
 * a second implementation of a safety guard, which is exactly how an unattended
 * path drifts away from the operator path.
 */
final readonly class DunningSchedulePolicy
{
    public function __construct(private DunningSchedule $schedule)
    {
    }

    /**
     * Is `$now` inside the organization's send window?
     *
     * The window is a range of whole hours: `[startHour, endHour)`. 09–18 means a
     * send may start at 09:00:00 and no later than 17:59:59. A window that wraps
     * midnight (start > end) is treated as closed rather than guessed at — the
     * settings UI does not offer it, and silently inventing overnight sending is
     * the opposite of what the window exists for.
     */
    public function isWindowOpen(DateTimeImmutable $now): bool
    {
        if ($this->schedule->windowStartHour >= $this->schedule->windowEndHour) {
            return false;
        }

        if ($this->schedule->isWeekdaysOnly && (int) $now->format('N') >= 6) {
            return false;
        }

        $hour = (int) $now->format('G');

        return $hour >= $this->schedule->windowStartHour && $hour < $this->schedule->windowEndHour;
    }

    /**
     * The stage an invoice this far past due has reached, or null if it has not
     * reached the first threshold yet (including anything not yet due).
     *
     * Thresholds are read highest-first so that a misconfiguration where the
     * thresholds are not ascending still escalates monotonically instead of
     * picking a random stage.
     */
    public function stageFor(int $daysPastDue): ?DunningStage
    {
        if ($daysPastDue >= $this->schedule->finalAfterDays) {
            return DunningStage::Final;
        }

        if ($daysPastDue >= $this->schedule->reminderAfterDays) {
            return DunningStage::Reminder;
        }

        if ($daysPastDue >= $this->schedule->initialAfterDays) {
            return DunningStage::Initial;
        }

        return null;
    }

    /**
     * Whole days between an invoice's due date and `$now`, or null when the
     * invoice carries no due date at all.
     *
     * `InvoiceItem::$dueAt` is nullable by the Invoice contract — an invoice may
     * be issued without one. "Days past due" is undefined for those, so they are
     * never scheduled candidates. An operator can still dun them by hand.
     */
    public function daysPastDue(?string $dueAt, DateTimeImmutable $now): ?int
    {
        if ($dueAt === null || trim($dueAt) === '') {
            return null;
        }

        $due = DateTimeImmutable::createFromFormat('Y-m-d', substr(trim($dueAt), 0, 10));

        if ($due === false) {
            return null;
        }

        $days = (int) $due->setTime(0, 0)->diff($now->setTime(0, 0))->format('%r%a');

        return $days;
    }

    /**
     * A `final` notice is never sent unattended: it is surfaced for an operator
     * to send by hand (design §5, pending an owner decision). Loosening this is a
     * one-line change; the reverse would not be, because messages already sent
     * cannot be recalled.
     */
    public function requiresApproval(DunningStage $stage): bool
    {
        return $stage === DunningStage::Final;
    }

    public function isEnabled(): bool
    {
        return $this->schedule->isEnabled;
    }

    public function maxPerRun(): int
    {
        return $this->schedule->maxPerRun;
    }
}
