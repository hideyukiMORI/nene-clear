<?php

declare(strict_types=1);

namespace NeneClear\User;

interface ListUsersUseCaseInterface
{
    public function execute(?int $organizationId, int $limit, int $offset): ListUsersOutput;
}
