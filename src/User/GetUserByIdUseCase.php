<?php

declare(strict_types=1);

namespace NeneClear\User;

final readonly class GetUserByIdUseCase implements GetUserByIdUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function execute(int $id, ?int $callerOrganizationId): User
    {
        $user = $this->users->findById($id);

        if ($user === null || $user->organizationId !== $callerOrganizationId) {
            throw new UserNotFoundException($id);
        }

        return $user;
    }
}
