<?php

declare(strict_types=1);

namespace NeneClear\Organization;

interface GetOrganizationByIdUseCaseInterface
{
    /**
     * @throws OrganizationNotFoundException
     */
    public function execute(int $id): Organization;
}
