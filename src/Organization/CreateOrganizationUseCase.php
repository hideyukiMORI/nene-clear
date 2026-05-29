<?php

declare(strict_types=1);

namespace NeneClear\Organization;

final readonly class CreateOrganizationUseCase implements CreateOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    public function execute(CreateOrganizationInput $input): CreateOrganizationOutput
    {
        if ($this->organizations->existsBySlug($input->slug)) {
            throw new OrganizationAlreadyExistsException($input->slug);
        }

        $id = $this->organizations->save(new Organization(slug: $input->slug, name: $input->name));

        return new CreateOrganizationOutput(id: $id, slug: $input->slug, name: $input->name);
    }
}
