<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

/**
 * A user's TOTP enrolment. `secret` is the plaintext Base32 seed in memory; the
 * repository encrypts it at rest (#195) and decrypts on read.
 */
final readonly class TotpSecret
{
    public function __construct(
        public int $userId,
        public string $secret,
        public bool $isEnabled,
        public int $failedAttempts = 0,
        public ?string $lockedUntil = null,
    ) {
    }
}
