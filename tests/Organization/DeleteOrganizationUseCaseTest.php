<?php

declare(strict_types=1);

namespace NeneClear\Tests\Organization;

use NeneClear\Organization\DeleteOrganizationUseCase;
use NeneClear\Organization\Organization;
use NeneClear\Organization\OrganizationNotFoundException;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Audit\InMemoryAuditRecorderFactory;
use NeneClear\Tests\Support\FakeTransactionManager;
use NeneClear\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class DeleteOrganizationUseCaseTest extends TestCase
{
    private InMemoryAuditEventRepository $audit;

    protected function setUp(): void
    {
        $this->audit = new InMemoryAuditEventRepository();
    }

    private function useCase(InMemoryOrganizationRepository $repo): DeleteOrganizationUseCase
    {
        return new DeleteOrganizationUseCase(new FakeTransactionManager(), fn () => $repo, new InMemoryAuditRecorderFactory($this->audit, new FixedClock()), new FixedClock('2026-06-01T10:00:00+00:00'));
    }

    public function test_deletes_existing_organization(): void
    {
        $repo = new InMemoryOrganizationRepository([new Organization(slug: 'acme', name: 'Acme Co')]);
        $useCase = $this->useCase($repo);

        $useCase->execute(1, 1);

        self::assertNull($repo->findById(1));
    }

    public function test_records_audit_event_with_prior_state(): void
    {
        $repo = new InMemoryOrganizationRepository([new Organization(slug: 'acme', name: 'Acme Co')]);
        $useCase = $this->useCase($repo);

        $useCase->execute(1, 1);

        self::assertCount(1, $this->audit->events);
        $event = $this->audit->events[0];
        self::assertSame('organization_deleted', $event->action);
        self::assertSame(1, $event->organizationId);
        self::assertIsArray($event->before);
        self::assertSame('acme', $event->before['slug']);
        self::assertSame('Acme Co', $event->before['name']);
    }

    public function test_unknown_id_throws_not_found(): void
    {
        $useCase = $this->useCase(new InMemoryOrganizationRepository());

        $this->expectException(OrganizationNotFoundException::class);
        $useCase->execute(9999, 1);
    }
}
