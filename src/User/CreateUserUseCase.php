<?php

declare(strict_types=1);

namespace NeneClear\User;

use NeneClear\Auth\Role;

final readonly class CreateUserUseCase implements CreateUserUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function execute(CreateUserInput $input): User
    {
        // Privilege-escalation guard: the cross-tenant superadmin role may only
        // exist with a null organization (created by a superadmin caller). An
        // org-scoped admin (non-null organizationId) cannot mint a superadmin.
        if ($input->role === Role::Superadmin && $input->organizationId !== null) {
            throw new RoleNotAssignableException($input->role->value);
        }

        if ($this->users->existsByEmail($input->email)) {
            throw new UserAlreadyExistsException($input->email);
        }

        // With a password the account is active; without one it is invited and
        // cannot log in until a password is set (the hash is a random placeholder).
        $status = $input->password !== null ? UserStatus::Active : UserStatus::Invited;
        $hash = password_hash($input->password ?? bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        $id = $this->users->save(new User(
            email: $input->email,
            role: $input->role,
            status: $status,
            passwordHash: $hash,
            organizationId: $input->organizationId,
        ));

        $created = $this->users->findById($id);

        if ($created === null) {
            throw new UserNotFoundException($id);
        }

        return $created;
    }
}
