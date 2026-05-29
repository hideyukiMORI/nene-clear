<?php

declare(strict_types=1);

namespace NeneClear\User;

use NeneClear\Auth\Role;

final readonly class UpdateUserInput
{
    public function __construct(
        public int $id,
        public ?int $callerOrganizationId,
        public ?Role $role,
        public ?UserStatus $status,
    ) {
    }
}
