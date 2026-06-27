<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use RuntimeException;

/** The MFA challenge token is missing, malformed, expired, or not a challenge token. */
final class MfaChallengeInvalidException extends RuntimeException
{
}
