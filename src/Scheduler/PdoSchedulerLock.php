<?php

declare(strict_types=1);

namespace NeneClear\Scheduler;

use DateTimeImmutable;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Throwable;

/**
 * {@see SchedulerLockInterface} on the `scheduler_locks` table (#400).
 *
 * The lock is held in the database rather than in a pid or lock file because
 * production is shared hosting: the filesystem is not a reliable arbiter there,
 * while the database is the one component every run certainly shares.
 *
 * A native advisory lock (MySQL `GET_LOCK`, PostgreSQL `pg_advisory_lock`) would
 * self-release on disconnect, but it does not exist in SQLite — so "two runs
 * never send the same notice" could not be *proved by a test*. An exclusion
 * mechanism that is only believed to work is the worst kind, because its failure
 * shows up as a real email that cannot be recalled. Hence a table, which behaves
 * identically on all three adapters and is exercised by the suite.
 */
final readonly class PdoSchedulerLock implements SchedulerLockInterface
{
    public function __construct(private DatabaseQueryExecutorInterface $query)
    {
    }

    public function acquire(string $key, string $holderToken, int $ttlSeconds, DateTimeImmutable $now): bool
    {
        $nowStr = $now->format('Y-m-d H:i:s');
        $expiresAt = $now->modify('+' . $ttlSeconds . ' seconds')->format('Y-m-d H:i:s');

        // Step 1 — reclaim: a single conditional UPDATE. Only a row that has
        // actually expired matches, and only one caller can win it, because the
        // UPDATE re-evaluates the condition under the row lock. This is not a
        // check-then-act: nothing is read and then acted upon.
        $reclaimed = $this->query->execute(
            'UPDATE scheduler_locks SET holder_token = ?, acquired_at = ?, expires_at = ? '
            . 'WHERE lock_key = ? AND expires_at <= ?',
            [$holderToken, $nowStr, $expiresAt, $key, $nowStr],
        );

        if ($reclaimed > 0) {
            return true;
        }

        // Step 2 — first taker: a single INSERT against the primary key. If a
        // live lock exists (or a competitor inserted first), the key violation is
        // the answer, not an error to report.
        try {
            $this->query->execute(
                'INSERT INTO scheduler_locks (lock_key, holder_token, acquired_at, expires_at) VALUES (?, ?, ?, ?)',
                [$key, $holderToken, $nowStr, $expiresAt],
            );

            return true;
        } catch (Throwable) {
            // Held by someone else. MySQL reports a no-op UPDATE as 0 affected
            // rows even when the row matched, so a reclaim that changed nothing
            // can also land here; either way the answer is the same.
            return false;
        }
    }

    public function release(string $key, string $holderToken): void
    {
        $this->query->execute(
            'DELETE FROM scheduler_locks WHERE lock_key = ? AND holder_token = ?',
            [$key, $holderToken],
        );
    }
}
