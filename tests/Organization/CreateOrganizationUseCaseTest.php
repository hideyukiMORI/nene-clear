<?php

declare(strict_types=1);

namespace NeneClear\Tests\Organization;

use NeneClear\Organization\CreateOrganizationInput;
use NeneClear\Organization\CreateOrganizationUseCase;
use NeneClear\Organization\Organization;
use NeneClear\Organization\OrganizationAlreadyExistsException;
use PHPUnit\Framework\TestCase;

final class CreateOrganizationUseCaseTest extends TestCase
{
    public function test_creates_organization_and_returns_output_with_id(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $useCase = new CreateOrganizationUseCase($repo);

        $output = $useCase->execute(new CreateOrganizationInput(slug: 'acme', name: 'Acme Co'));

        self::assertGreaterThan(0, $output->id);
        self::assertSame('acme', $output->slug);
        self::assertSame('Acme Co', $output->name);
        self::assertNotNull($repo->findBySlug('acme'));
    }

    public function test_rejects_duplicate_slug(): void
    {
        $repo = new InMemoryOrganizationRepository([new Organization(slug: 'acme', name: 'Acme Co')]);
        $useCase = new CreateOrganizationUseCase($repo);

        $this->expectException(OrganizationAlreadyExistsException::class);

        $useCase->execute(new CreateOrganizationInput(slug: 'acme', name: 'Acme Again'));
    }
}
