<?php

declare(strict_types=1);

namespace NeneClear\User;

use RuntimeException;

/**
 * Thrown when a caller tries to assign a role they are not permitted to grant —
 * specifically, assigning the cross-tenant `superadmin` role to an
 * organization-scoped user. Prevents privilege escalation from an org admin to
 * a platform superadmin. Maps to HTTP 403.
 */
final class RoleNotAssignableException extends RuntimeException
{
    public function __construct(public readonly string $role)
    {
        parent::__construct(sprintf('Role "%s" cannot be assigned in this context.', $role));
    }
}
