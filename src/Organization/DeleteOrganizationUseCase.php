<?php

declare(strict_types=1);

namespace NeneClear\Organization;

final readonly class DeleteOrganizationUseCase implements DeleteOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    public function execute(int $id): void
    {
        if ($this->organizations->findById($id) === null) {
            throw new OrganizationNotFoundException($id);
        }

        $this->organizations->delete($id);
    }
}
