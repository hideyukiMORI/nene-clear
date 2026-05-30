<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use DateTimeImmutable;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;

/**
 * DB-backed login throttle. After {@see MAX_ATTEMPTS} failures within
 * {@see WINDOW_SECONDS}, the identifier is locked for {@see LOCK_SECONDS}.
 *
 * The identifier is hashed before storage so raw emails/IPs are never persisted
 * in the throttle table.
 */
final readonly class PdoLoginThrottle implements LoginThrottleInterface
{
    private const int MAX_ATTEMPTS = 5;
    private const int WINDOW_SECONDS = 900;  // 15 minutes
    private const int LOCK_SECONDS = 900;    // 15 minutes

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private ClockInterface $clock,
    ) {
    }

    public function secondsUntilUnlocked(string $identifier): int
    {
        $row = $this->query->fetchOne(
            'SELECT locked_until FROM login_attempts WHERE identifier_hash = ?',
            [$this->hash($identifier)],
        );

        if ($row === null || ($row['locked_until'] ?? null) === null) {
            return 0;
        }

        $now = $this->clock->now();
        $lockedUntil = new DateTimeImmutable((string) $row['locked_until']);
        $remaining = $lockedUntil->getTimestamp() - $now->getTimestamp();

        return max(0, $remaining);
    }

    public function recordFailure(string $identifier): void
    {
        $hash = $this->hash($identifier);
        $now = $this->clock->now();
        $nowStr = $now->format('Y-m-d H:i:s');

        $row = $this->query->fetchOne(
            'SELECT attempt_count, window_started_at FROM login_attempts WHERE identifier_hash = ?',
            [$hash],
        );

        if ($row === null) {
            $this->query->execute(
                'INSERT INTO login_attempts (identifier_hash, attempt_count, window_started_at, locked_until) VALUES (?, 1, ?, NULL)',
                [$hash, $nowStr],
            );

            return;
        }

        $windowStart = new DateTimeImmutable((string) $row['window_started_at']);
        $windowAge = $now->getTimestamp() - $windowStart->getTimestamp();

        // Window expired → start a fresh window.
        if ($windowAge > self::WINDOW_SECONDS) {
            $this->query->execute(
                'UPDATE login_attempts SET attempt_count = 1, window_started_at = ?, locked_until = NULL WHERE identifier_hash = ?',
                [$nowStr, $hash],
            );

            return;
        }

        $count = (int) $row['attempt_count'] + 1;
        $lockedUntil = $count >= self::MAX_ATTEMPTS
            ? $now->modify('+' . self::LOCK_SECONDS . ' seconds')->format('Y-m-d H:i:s')
            : null;

        $this->query->execute(
            'UPDATE login_attempts SET attempt_count = ?, locked_until = ? WHERE identifier_hash = ?',
            [$count, $lockedUntil, $hash],
        );
    }

    public function clear(string $identifier): void
    {
        $this->query->execute(
            'DELETE FROM login_attempts WHERE identifier_hash = ?',
            [$this->hash($identifier)],
        );
    }

    private function hash(string $identifier): string
    {
        return hash('sha256', strtolower($identifier));
    }
}
