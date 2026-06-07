<?php

declare(strict_types=1);

namespace NeneClear\User;

use RuntimeException;

/** The invitation token was valid but has passed its expiry. */
final class InvitationExpiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The invitation token has expired.');
    }
}
