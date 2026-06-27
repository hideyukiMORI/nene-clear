<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use RuntimeException;

/** The user has no enabled TOTP enrolment to verify against. */
final class TotpNotEnabledException extends RuntimeException
{
}
