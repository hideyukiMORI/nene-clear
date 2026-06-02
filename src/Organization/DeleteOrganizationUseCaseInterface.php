<?php

declare(strict_types=1);

namespace NeneClear\Organization;

interface DeleteOrganizationUseCaseInterface
{
    /**
     * @throws OrganizationNotFoundException
     */
    public function execute(int $id, int $actorUserId): void;
}
