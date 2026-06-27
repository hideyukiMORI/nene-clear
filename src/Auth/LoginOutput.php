<?php

declare(strict_types=1);

namespace NeneClear\Auth;

final readonly class LoginOutput
{
    public function __construct(
        public ?string $token,
        public int $userId,
        public string $email,
        public string $role,
        public ?int $organizationId,
        public bool $mfaRequired = false,
        public ?string $mfaToken = null,
    ) {
    }
}
