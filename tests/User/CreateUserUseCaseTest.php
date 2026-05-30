<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\Auth\Role;
use NeneClear\User\CreateUserInput;
use NeneClear\User\CreateUserUseCase;
use NeneClear\User\User;
use NeneClear\User\UserAlreadyExistsException;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class CreateUserUseCaseTest extends TestCase
{
    public function test_with_password_creates_active_user(): void
    {
        $repo = new InMemoryUserRepository();
        $useCase = new CreateUserUseCase($repo);

        $user = $useCase->execute(new CreateUserInput(
            organizationId: 7,
            email: 'member@acme.example',
            role: Role::Member,
            password: 'secret-pass',
        ));

        self::assertGreaterThan(0, $user->id);
        self::assertSame('member@acme.example', $user->email);
        self::assertSame(Role::Member, $user->role);
        self::assertSame(UserStatus::Active, $user->status);
        self::assertSame(7, $user->organizationId);
        self::assertTrue(password_verify('secret-pass', $user->passwordHash));
    }

    public function test_without_password_creates_invited_user_with_unusable_hash(): void
    {
        $repo = new InMemoryUserRepository();
        $useCase = new CreateUserUseCase($repo);

        $user = $useCase->execute(new CreateUserInput(
            organizationId: 7,
            email: 'invited@acme.example',
            role: Role::Viewer,
            password: null,
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
        $useCase = new CreateUserUseCase($repo);

        $this->expectException(UserAlreadyExistsException::class);

        $useCase->execute(new CreateUserInput(
            organizationId: 7,
            email: 'taken@acme.example',
            role: Role::Member,
            password: 'p',
        ));
    }

    public function test_superadmin_user_has_null_organization(): void
    {
        $repo = new InMemoryUserRepository();
        $useCase = new CreateUserUseCase($repo);

        $user = $useCase->execute(new CreateUserInput(
            organizationId: null,
            email: 'root@platform.example',
            role: Role::Superadmin,
            password: 'p',
        ));

        self::assertNull($user->organizationId);
        self::assertSame(Role::Superadmin, $user->role);
    }
}
