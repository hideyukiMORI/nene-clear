<?php

declare(strict_types=1);

namespace NeneClear\User;

use NeneClear\Auth\Role;

/**
 * Operator account. `organizationId` is null only for a cross-tenant superadmin
 * (ADR 0006). `id` is null before persistence.
 */
final readonly class User
{
    public function __construct(
        public string $email,
        public Role $role,
        public UserStatus $status,
        public string $passwordHash,
        public ?int $organizationId = null,
        public ?int $id = null,
    ) {
    }
}
