<?php

declare(strict_types=1);

namespace NeneClear\User;

/**
 * User account status (terminology.md §2).
 */
enum UserStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
}
