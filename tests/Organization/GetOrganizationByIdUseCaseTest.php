<?php

declare(strict_types=1);

namespace NeneClear\Tests\Organization;

use NeneClear\Organization\GetOrganizationByIdUseCase;
use NeneClear\Organization\Organization;
use NeneClear\Organization\OrganizationNotFoundException;
use PHPUnit\Framework\TestCase;

final class GetOrganizationByIdUseCaseTest extends TestCase
{
    public function test_returns_existing_organization(): void
    {
        $repo = new InMemoryOrganizationRepository([new Organization(slug: 'acme', name: 'Acme Co')]);
        $useCase = new GetOrganizationByIdUseCase($repo);

        $org = $useCase->execute(1);

        self::assertSame(1, $org->id);
        self::assertSame('acme', $org->slug);
    }

    public function test_unknown_id_throws_not_found(): void
    {
        $useCase = new GetOrganizationByIdUseCase(new InMemoryOrganizationRepository());

        $this->expectException(OrganizationNotFoundException::class);
        $useCase->execute(9999);
    }
}
