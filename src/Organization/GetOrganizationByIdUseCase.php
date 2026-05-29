<?php

declare(strict_types=1);

namespace NeneClear\Organization;

final readonly class GetOrganizationByIdUseCase implements GetOrganizationByIdUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    public function execute(int $id): Organization
    {
        $organization = $this->organizations->findById($id);

        if ($organization === null) {
            throw new OrganizationNotFoundException($id);
        }

        return $organization;
    }
}
