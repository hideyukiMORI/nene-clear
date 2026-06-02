<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\Auth\Role;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\User\UpdateUserInput;
use NeneClear\User\UpdateUserUseCase;
use NeneClear\User\User;
use NeneClear\User\UserNotFoundException;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class UpdateUserUseCaseTest extends TestCase
{
    private InMemoryAuditEventRepository $audit;

    protected function setUp(): void
    {
        $this->audit = new InMemoryAuditEventRepository();
    }

    private function useCase(InMemoryUserRepository $repo): UpdateUserUseCase
    {
        return new UpdateUserUseCase($repo, $this->audit, new FixedClock('2026-06-01T10:00:00+00:00'));
    }

    private function seedUser(int $orgId = 7): InMemoryUserRepository
    {
        return new InMemoryUserRepository([
            new User(
                email: 'member@acme.example',
                role: Role::Member,
                status: UserStatus::Invited,
                passwordHash: 'hash',
                organizationId: $orgId,
                id: 1,
            ),
        ]);
    }

    public function test_updates_role_and_status(): void
    {
        $useCase = $this->useCase($this->seedUser());

        $updated = $useCase->execute(new UpdateUserInput(
            id: 1,
            callerOrganizationId: 7,
            role: Role::Admin,
            status: UserStatus::Active,
            actorUserId: 42,
        ));

        self::assertSame(Role::Admin, $updated->role);
        self::assertSame(UserStatus::Active, $updated->status);
        // Email and password are preserved.
        self::assertSame('member@acme.example', $updated->email);
        self::assertSame('hash', $updated->passwordHash);
    }

    public function test_records_audit_event_with_before_and_after(): void
    {
        $useCase = $this->useCase($this->seedUser());

        $useCase->execute(new UpdateUserInput(
            id: 1,
            callerOrganizationId: 7,
            role: Role::Admin,
            status: UserStatus::Active,
            actorUserId: 42,
        ));

        self::assertCount(1, $this->audit->events);
        $event = $this->audit->events[0];
        self::assertSame('user_updated', $event->eventType);
        self::assertSame(42, $event->actorUserId);
        self::assertSame('member', $event->payload['before']['role']);
        self::assertSame('invited', $event->payload['before']['status']);
        self::assertSame('admin', $event->payload['after']['role']);
        self::assertSame('active', $event->payload['after']['status']);
    }

    public function test_null_fields_preserve_existing_values(): void
    {
        $useCase = $this->useCase($this->seedUser());

        $updated = $useCase->execute(new UpdateUserInput(
            id: 1,
            callerOrganizationId: 7,
            role: null,
            status: null,
            actorUserId: 42,
        ));

        self::assertSame(Role::Member, $updated->role);
        self::assertSame(UserStatus::Invited, $updated->status);
    }

    public function test_cross_tenant_update_throws_not_found(): void
    {
        $useCase = $this->useCase($this->seedUser(orgId: 7));

        $this->expectException(UserNotFoundException::class);

        $useCase->execute(new UpdateUserInput(
            id: 1,
            callerOrganizationId: 999,
            role: Role::Admin,
            status: null,
            actorUserId: 42,
        ));
    }

    public function test_unknown_user_throws_not_found(): void
    {
        $useCase = $this->useCase(new InMemoryUserRepository());

        $this->expectException(UserNotFoundException::class);

        $useCase->execute(new UpdateUserInput(
            id: 9999,
            callerOrganizationId: 7,
            role: Role::Admin,
            status: null,
            actorUserId: 42,
        ));
    }

    public function test_cannot_promote_org_user_to_superadmin(): void
    {
        $useCase = $this->useCase($this->seedUser(orgId: 7));

        $this->expectException(\NeneClear\User\RoleNotAssignableException::class);

        $useCase->execute(new UpdateUserInput(
            id: 1,
            callerOrganizationId: 7,
            role: Role::Superadmin,
            status: null,
            actorUserId: 42,
        ));
    }
}
