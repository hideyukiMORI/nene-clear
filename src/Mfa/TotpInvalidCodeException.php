<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use RuntimeException;

/** The supplied TOTP code did not verify (wrong, malformed, or replayed). */
final class TotpInvalidCodeException extends RuntimeException
{
}
