<?php

declare(strict_types=1);

namespace NeneClear\User;

final readonly class DeleteUserUseCase implements DeleteUserUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function execute(int $id, ?int $callerOrganizationId): void
    {
        $user = $this->users->findById($id);

        if ($user === null || $user->organizationId !== $callerOrganizationId) {
            throw new UserNotFoundException($id);
        }

        $this->users->delete($callerOrganizationId, $id);
    }
}
