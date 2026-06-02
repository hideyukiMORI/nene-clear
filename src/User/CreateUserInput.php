<?php

declare(strict_types=1);

namespace NeneClear\User;

use NeneClear\Auth\Role;

final readonly class CreateUserInput
{
    public function __construct(
        public ?int $organizationId,
        public string $email,
        public Role $role,
        public ?string $password,
        public int $actorUserId,
    ) {
    }
}
