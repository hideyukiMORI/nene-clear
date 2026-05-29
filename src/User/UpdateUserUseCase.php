<?php

declare(strict_types=1);

namespace NeneClear\User;

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
