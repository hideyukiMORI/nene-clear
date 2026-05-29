<?php

declare(strict_types=1);

namespace NeneClear\User;

use RuntimeException;

final class UserAlreadyExistsException extends RuntimeException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct(sprintf('A user with email "%s" already exists.', $email));
    }
}
