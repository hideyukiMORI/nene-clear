<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

/**
 * Per-organization schedule for unattended dunning (#400,
 * `docs/development/dunning-scheduler-design.md`).
 *
 * This object says *when* a run may send and at which escalation stage. It says
 * nothing about whether a particular invoice may be dunned — every safety guard
 * (upstream status, pause, minimum interval) stays in
 * {@see \NeneClear\Dunning\SendDunningUseCase}, which the scheduler must call.
 *
 * Defaults mirror the migration defaults, so an organization that has never
 * touched its settings behaves exactly as before: disabled.
 */
final readonly class DunningSchedule
{
    public function __construct(
        public bool $isEnabled = false,
        public int $initialAfterDays = 3,
        public int $reminderAfterDays = 14,
        public int $finalAfterDays = 30,
        public int $windowStartHour = 9,
        public int $windowEndHour = 18,
        public bool $isWeekdaysOnly = true,
        public int $maxPerRun = 50,
    ) {
    }
}
