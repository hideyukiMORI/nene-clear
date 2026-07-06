<?php

declare(strict_types=1);

namespace NeneClear\Tests\Organization;

use NeneClear\Organization\CreateOrganizationInput;
use NeneClear\Organization\CreateOrganizationUseCase;
use NeneClear\Organization\Organization;
use NeneClear\Organization\OrganizationAlreadyExistsException;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Audit\InMemoryAuditRecorderFactory;
use NeneClear\Tests\Support\FakeTransactionManager;
use NeneClear\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class CreateOrganizationUseCaseTest extends TestCase
{
    private InMemoryAuditEventRepository $audit;

    protected function setUp(): void
    {
        $this->audit = new InMemoryAuditEventRepository();
    }

    private function useCase(InMemoryOrganizationRepository $repo): CreateOrganizationUseCase
    {
        return new CreateOrganizationUseCase(new FakeTransactionManager(), fn () => $repo, new InMemoryAuditRecorderFactory($this->audit, new FixedClock()), new FixedClock('2026-06-01T10:00:00+00:00'));
    }

    public function test_creates_organization_and_returns_output_with_id(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $useCase = $this->useCase($repo);

        $output = $useCase->execute(new CreateOrganizationInput(slug: 'acme', name: 'Acme Co', actorUserId: 1));

        self::assertGreaterThan(0, $output->id);
        self::assertSame('acme', $output->slug);
        self::assertSame('Acme Co', $output->name);
        self::assertNotNull($repo->findBySlug('acme'));
    }

    public function test_records_audit_event_scoped_to_new_tenant(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $useCase = $this->useCase($repo);

        $output = $useCase->execute(new CreateOrganizationInput(slug: 'acme', name: 'Acme Co', actorUserId: 1));

        self::assertCount(1, $this->audit->events);
        $event = $this->audit->events[0];
        self::assertSame('organization_created', $event->action);
        self::assertSame($output->id, $event->organizationId);
        self::assertSame(1, $event->actorId);
        self::assertIsArray($event->after);
        self::assertSame('acme', $event->after['slug']);
    }

    public function test_rejects_duplicate_slug(): void
    {
        $repo = new InMemoryOrganizationRepository([new Organization(slug: 'acme', name: 'Acme Co')]);
        $useCase = $this->useCase($repo);

        $this->expectException(OrganizationAlreadyExistsException::class);

        $useCase->execute(new CreateOrganizationInput(slug: 'acme', name: 'Acme Again', actorUserId: 1));
    }
}
