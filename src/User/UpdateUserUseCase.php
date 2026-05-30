<?php

declare(strict_types=1);

namespace NeneClear\User;

use NeneClear\Auth\Role;

final readonly class UpdateUserUseCase implements UpdateUserUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function execute(UpdateUserInput $input): User
    {
        $existing = $this->users->findById($input->id);

        if ($existing === null || $existing->organizationId !== $input->callerOrganizationId) {
            throw new UserNotFoundException($input->id);
        }

        // Privilege-escalation guard: an org-scoped user (non-null org) can never
        // be promoted to the cross-tenant superadmin role.
        if ($input->role === Role::Superadmin && $existing->organizationId !== null) {
            throw new RoleNotAssignableException($input->role->value);
        }

        $updated = new User(
            email: $existing->email,
            role: $input->role ?? $existing->role,
            status: $input->status ?? $existing->status,
            passwordHash: $existing->passwordHash,
            organizationId: $existing->organizationId,
            id: $existing->id,
        );

        $this->users->save($updated);

        return $updated;
    }
}
