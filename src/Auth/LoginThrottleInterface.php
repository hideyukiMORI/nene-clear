<?php

declare(strict_types=1);

namespace NeneClear\Auth;

/**
 * Brute-force / credential-stuffing guard for the login endpoint. Tracks failed
 * attempts per identifier (email + client IP) and locks the identifier once a
 * threshold is exceeded within a rolling window.
 */
interface LoginThrottleInterface
{
    /**
     * Seconds remaining on an active lock, or 0 when not locked.
     */
    public function secondsUntilUnlocked(string $identifier): int;

    /**
     * Record a failed attempt. Engages a lock when the threshold is reached.
     */
    public function recordFailure(string $identifier): void;

    /**
     * Clear all attempt state for an identifier (called on successful login).
     */
    public function clear(string $identifier): void;
}
