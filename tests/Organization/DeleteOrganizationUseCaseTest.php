<?php

declare(strict_types=1);

namespace NeneClear\Tests\Organization;

use NeneClear\Organization\DeleteOrganizationUseCase;
use NeneClear\Organization\Organization;
use NeneClear\Organization\OrganizationNotFoundException;
use PHPUnit\Framework\TestCase;

final class DeleteOrganizationUseCaseTest extends TestCase
{
    public function test_deletes_existing_organization(): void
    {
        $repo = new InMemoryOrganizationRepository([new Organization(slug: 'acme', name: 'Acme Co')]);
        $useCase = new DeleteOrganizationUseCase($repo);

        $useCase->execute(1);

        self::assertNull($repo->findById(1));
    }

    public function test_unknown_id_throws_not_found(): void
    {
        $useCase = new DeleteOrganizationUseCase(new InMemoryOrganizationRepository());

        $this->expectException(OrganizationNotFoundException::class);
        $useCase->execute(9999);
    }
}
