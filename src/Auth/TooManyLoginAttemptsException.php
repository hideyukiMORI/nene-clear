<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use RuntimeException;

/**
 * Thrown when an identifier (email + client IP) exceeds the allowed number of
 * failed login attempts within the window. Maps to HTTP 429.
 */
final class TooManyLoginAttemptsException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many login attempts.');
    }
}
