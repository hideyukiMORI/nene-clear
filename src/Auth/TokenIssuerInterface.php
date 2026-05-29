<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use NeneClear\User\User;

interface TokenIssuerInterface
{
    public function issueForUser(User $user): string;
}
