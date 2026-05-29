<?php

declare(strict_types=1);

namespace NeneClear\Organization;

interface ListOrganizationsUseCaseInterface
{
    public function execute(int $limit, int $offset): ListOrganizationsOutput;
}
