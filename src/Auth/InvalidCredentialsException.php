<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use RuntimeException;

/**
 * Login failed. The message is intentionally generic so it never reveals
 * whether the email exists.
 */
final class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email or password is incorrect.');
    }
}
