<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use RuntimeException;

/** TOTP is already enabled for the user (enrol again only after disabling). */
final class TotpAlreadyEnabledException extends RuntimeException
{
}
