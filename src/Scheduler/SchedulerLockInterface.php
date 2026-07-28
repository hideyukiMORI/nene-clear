<?php

declare(strict_types=1);

namespace NeneClear\Scheduler;

use DateTimeImmutable;

/**
 * Mutual exclusion for unattended runs (#400,
 * `docs/development/dunning-scheduler-design.md` §8).
 *
 * A cron tick that overlaps a still-running previous tick is normal operation,
 * not an error: the second run simply does nothing. What must never happen is
 * two runs doing the same work — for dunning that means a duplicate email, which
 * cannot be recalled once sent.
 */
interface SchedulerLockInterface
{
    /**
     * Take `$key` for `$ttlSeconds`, or report that someone else holds it.
     *
     * Implementations MUST take the lock with a single atomic statement. An
     * expired lock may be reclaimed by the same statement — a run that was killed
     * mid-flight never got to release it, and must not block the job forever.
     *
     * @param string $holderToken identifies this run, so only it can release the lock
     */
    public function acquire(string $key, string $holderToken, int $ttlSeconds, DateTimeImmutable $now): bool;

    /**
     * Release `$key`, but only if `$holderToken` still holds it.
     *
     * The token check matters: without it, a run that overran its TTL would
     * release the lock its successor is legitimately holding.
     */
    public function release(string $key, string $holderToken): void;
}
