<?php

declare(strict_types=1);

namespace NeneClear\User;

final readonly class AcceptInvitationInput
{
    public function __construct(
        public string $rawToken,
        public string $password,
    ) {
    }
}
