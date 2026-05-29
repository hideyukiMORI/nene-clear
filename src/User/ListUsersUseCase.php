<?php

declare(strict_types=1);

namespace NeneClear\User;

final readonly class ListUsersUseCase implements ListUsersUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function execute(?int $organizationId, int $limit, int $offset): ListUsersOutput
    {
        return new ListUsersOutput(
            items: $this->users->findAllByOrganization($organizationId, $limit, $offset),
            total: $this->users->countByOrganization($organizationId),
            limit: $limit,
            offset: $offset,
        );
    }
}
