<?php

declare(strict_types=1);

namespace NeneClear\User;

interface DeleteUserUseCaseInterface
{
    /**
     * @throws UserNotFoundException
     */
    public function execute(int $id, ?int $callerOrganizationId, int $actorUserId): void;
}
