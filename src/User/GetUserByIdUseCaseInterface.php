<?php

declare(strict_types=1);

namespace NeneClear\User;

interface GetUserByIdUseCaseInterface
{
    /**
     * Scoped to the caller's organization; a user in another tenant is treated
     * as not found.
     *
     * @throws UserNotFoundException
     */
    public function execute(int $id, ?int $callerOrganizationId): User;
}
