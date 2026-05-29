<?php

declare(strict_types=1);

namespace NeneClear\User;

use RuntimeException;

final class UserNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('User %d was not found.', $id));
    }
}
