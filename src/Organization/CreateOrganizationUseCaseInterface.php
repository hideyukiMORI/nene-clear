<?php

declare(strict_types=1);

namespace NeneClear\Organization;

interface CreateOrganizationUseCaseInterface
{
    public function execute(CreateOrganizationInput $input): CreateOrganizationOutput;
}
