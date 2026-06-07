<?php

declare(strict_types=1);

namespace NeneClear\User;

use RuntimeException;

/**
 * The invitation token is unknown, malformed, or already accepted. Deliberately
 * does not distinguish which, to avoid token/account enumeration.
 */
final class InvitationInvalidException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The invitation token is invalid.');
    }
}
