<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use RuntimeException;

/** TOTP verification is locked after too many failures; codes are not checked while locked. */
final class TotpLockedException extends RuntimeException
{
    public function __construct(public readonly string $lockedUntil)
    {
        parent::__construct('TOTP verification is temporarily locked.');
    }
}
