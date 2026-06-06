<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\Auth\Role;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Support\FakeTransactionManager;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\User\CreateUserInput;
use NeneClear\User\CreateUserUseCase;
use NeneClear\User\User;
use NeneClear\User\UserAlreadyExistsException;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class CreateUserUseCaseTest extends TestCase
{
    private InMemoryAuditEventRepository $audit;

    protected function setUp(): void
    {
        $this->audit = new InMemoryAuditEventRepository();
    }

    private function useCase(InMemoryUserRepository $repo): CreateUserUseCase
    {
        return new CreateUserUseCase(new FakeTransactionManager(), fn () => $repo, fn () => $this->audit, new FixedClock('2026-06-01T10:00:00+00:00'));
    }

    public function test_with_password_creates_active_user(): void
    {
        $useCase = $this->useCase(new InMemoryUserRepository());

        $user = $useCase->execute(new CreateUserInput(
            organizationId: 7,
            email: 'member@acme.example',
            role: Role::Member,
            password: 'secret-pass',
            actorUserId: 42,
        ));

        self::assertGreaterThan(0, $user->id);
        self::assertSame('member@acme.example', $user->email);
        self::assertSame(Role::Member, $user->role);
        self::assertSame(UserStatus::Active, $user->status);
        self::assertSame(7, $user->organizationId);
        self::assertTrue(password_verify('secret-pass', $user->passwordHash));
    }

    public function test_records_audit_event_without_leaking_password(): void
    {
        $useCase = $this->useCase(new InMemoryUserRepository());

        $useCase->execute(new CreateUserInput(
            organizationId: 7,
            email: 'member@acme.example',
            role: Role::Member,
            password: 'secret-pass',
            actorUserId: 42,
        ));

        self::assertCount(1, $this->audit->events);
        $event = $this->audit->events[0];
        self::assertSame('user_created', $event->eventType);
        self::assertSame(42, $event->actorUserId);
        self::assertSame(7, $event->organizationId);
        self::assertSame('member@acme.example', $event->payload['after']['email']);
        self::assertSame('active', $event->payload['after']['status']);
        // The password / hash must never appear anywhere in the audit payload.
        $encoded = json_encode($event->payload, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsStringIgnoringCase('secret-pass', $encoded);
        self::assertStringNotContainsStringIgnoringCase('password', $encoded);
    }

    public function test_without_password_creates_invited_user_with_unusable_hash(): void
    {
        $useCase = $this->useCase(new InMemoryUserRepository());

        $user = $useCase->execute(new CreateUserInput(
            organizationId: 7,
            email: 'invited@acme.example',
            role: Role::Viewer,
            password: null,
            actorUserId: 42,
        ));

        self::assertSame(UserStatus::Invited, $user->status);
        // A random placeholder hash must not verify against an empty password.
        self::assertFalse(password_verify('', $user->passwordHash));
        self::assertNotSame('', $user->passwordHash);
    }

    public function test_rejects_duplicate_email(): void
    {
        $repo = new InMemoryUserRepository([
            new User(
                email: 'taken@acme.example',
                role: Role::Member,
                status: UserStatus::Active,
                passwordHash: 'x',
                organizationId: 7,
            ),
        ]);
        $useCase = $this->useCase($repo);

        $this->expectException(UserAlreadyExistsException::class);

        $useCase->execute(new CreateUserInput(
            organizationId: 7,
            email: 'taken@acme.example',
            role: Role::Member,
            password: 'p',
            actorUserId: 42,
        ));
    }

    public function test_superadmin_user_has_null_organization(): void
    {
        $useCase = $this->useCase(new InMemoryUserRepository());

        $user = $useCase->execute(new CreateUserInput(
            organizationId: null,
            email: 'root@platform.example',
            role: Role::Superadmin,
            password: 'p',
            actorUserId: 1,
        ));

        self::assertNull($user->organizationId);
        self::assertSame(Role::Superadmin, $user->role);
    }

    public function test_org_scoped_caller_cannot_mint_superadmin(): void
    {
        $useCase = $this->useCase(new InMemoryUserRepository());

        // An org-scoped admin (non-null org) assigning superadmin is privilege
        // escalation and must be rejected.
        $this->expectException(\NeneClear\User\RoleNotAssignableException::class);

        $useCase->execute(new CreateUserInput(
            organizationId: 7,
            email: 'escalate@org.example',
            role: Role::Superadmin,
            password: 'p',
            actorUserId: 42,
        ));
    }
}
