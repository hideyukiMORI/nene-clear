<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\Auth\Role;
use NeneClear\User\UpdateUserInput;
use NeneClear\User\UpdateUserUseCase;
use NeneClear\User\User;
use NeneClear\User\UserNotFoundException;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class UpdateUserUseCaseTest extends TestCase
{
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
        $repo = $this->seedUser();
        $useCase = new UpdateUserUseCase($repo);

        $updated = $useCase->execute(new UpdateUserInput(
            id: 1,
            callerOrganizationId: 7,
            role: Role::Admin,
            status: UserStatus::Active,
        ));

        self::assertSame(Role::Admin, $updated->role);
        self::assertSame(UserStatus::Active, $updated->status);
        // Email and password are preserved.
        self::assertSame('member@acme.example', $updated->email);
        self::assertSame('hash', $updated->passwordHash);
    }

    public function test_null_fields_preserve_existing_values(): void
    {
        $repo = $this->seedUser();
        $useCase = new UpdateUserUseCase($repo);

        $updated = $useCase->execute(new UpdateUserInput(
            id: 1,
            callerOrganizationId: 7,
            role: null,
            status: null,
        ));

        self::assertSame(Role::Member, $updated->role);
        self::assertSame(UserStatus::Invited, $updated->status);
    }

    public function test_cross_tenant_update_throws_not_found(): void
    {
        $repo = $this->seedUser(orgId: 7);
        $useCase = new UpdateUserUseCase($repo);

        $this->expectException(UserNotFoundException::class);

        $useCase->execute(new UpdateUserInput(
            id: 1,
            callerOrganizationId: 999,
            role: Role::Admin,
            status: null,
        ));
    }

    public function test_unknown_user_throws_not_found(): void
    {
        $repo = new InMemoryUserRepository();
        $useCase = new UpdateUserUseCase($repo);

        $this->expectException(UserNotFoundException::class);

        $useCase->execute(new UpdateUserInput(
            id: 9999,
            callerOrganizationId: 7,
            role: Role::Admin,
            status: null,
        ));
    }

    public function test_cannot_promote_org_user_to_superadmin(): void
    {
        $repo = $this->seedUser(orgId: 7);
        $useCase = new UpdateUserUseCase($repo);

        $this->expectException(\NeneClear\User\RoleNotAssignableException::class);

        $useCase->execute(new UpdateUserInput(
            id: 1,
            callerOrganizationId: 7,
            role: Role::Superadmin,
            status: null,
        ));
    }
}
