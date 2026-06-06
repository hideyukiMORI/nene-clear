<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\Audit\AuditRecorder;
use NeneClear\Auth\Role;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Support\FakeTransactionManager;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\User\DeleteUserUseCase;
use NeneClear\User\User;
use NeneClear\User\UserNotFoundException;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class DeleteUserUseCaseTest extends TestCase
{
    private InMemoryAuditEventRepository $audit;

    protected function setUp(): void
    {
        $this->audit = new InMemoryAuditEventRepository();
    }

    private function useCase(InMemoryUserRepository $repo): DeleteUserUseCase
    {
        return new DeleteUserUseCase(new FakeTransactionManager(), fn () => $repo, fn () => new AuditRecorder($this->audit), new FixedClock('2026-06-01T10:00:00+00:00'));
    }

    private function seedUser(int $orgId = 7): InMemoryUserRepository
    {
        return new InMemoryUserRepository([
            new User(
                email: 'member@acme.example',
                role: Role::Member,
                status: UserStatus::Active,
                passwordHash: 'hash',
                organizationId: $orgId,
                id: 1,
            ),
        ]);
    }

    public function test_deletes_own_org_user(): void
    {
        $repo = $this->seedUser();
        $useCase = $this->useCase($repo);

        $useCase->execute(1, 7, 42);

        self::assertNull($repo->findById(1));
    }

    public function test_records_audit_event_with_prior_state(): void
    {
        $repo = $this->seedUser();
        $useCase = $this->useCase($repo);

        $useCase->execute(1, 7, 42);

        self::assertCount(1, $this->audit->events);
        $event = $this->audit->events[0];
        self::assertSame('user_deleted', $event->eventType);
        self::assertSame(42, $event->actorUserId);
        self::assertSame('member@acme.example', $event->payload['before']['email']);
        self::assertSame('member', $event->payload['before']['role']);
    }

    public function test_cross_tenant_delete_throws_not_found_and_keeps_user(): void
    {
        $repo = $this->seedUser(orgId: 7);
        $useCase = $this->useCase($repo);

        try {
            $useCase->execute(1, 999, 42);
            self::fail('Expected UserNotFoundException');
        } catch (UserNotFoundException) {
            // User must still exist — a foreign tenant cannot delete it.
            self::assertNotNull($repo->findById(1));
        }

        self::assertCount(0, $this->audit->events);
    }

    public function test_unknown_user_throws_not_found(): void
    {
        $useCase = $this->useCase(new InMemoryUserRepository());

        $this->expectException(UserNotFoundException::class);

        $useCase->execute(9999, 7, 42);
    }
}
