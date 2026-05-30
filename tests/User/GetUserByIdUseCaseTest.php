<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\Auth\Role;
use NeneClear\User\GetUserByIdUseCase;
use NeneClear\User\User;
use NeneClear\User\UserNotFoundException;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class GetUserByIdUseCaseTest extends TestCase
{
    private function seedUser(?int $orgId = 7): InMemoryUserRepository
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

    public function test_returns_own_org_user(): void
    {
        $useCase = new GetUserByIdUseCase($this->seedUser());

        $user = $useCase->execute(1, 7);

        self::assertSame(1, $user->id);
        self::assertSame('member@acme.example', $user->email);
    }

    public function test_cross_tenant_throws_not_found(): void
    {
        $useCase = new GetUserByIdUseCase($this->seedUser(orgId: 7));

        $this->expectException(UserNotFoundException::class);
        $useCase->execute(1, 999);
    }

    public function test_unknown_user_throws_not_found(): void
    {
        $useCase = new GetUserByIdUseCase(new InMemoryUserRepository());

        $this->expectException(UserNotFoundException::class);
        $useCase->execute(9999, 7);
    }

    public function test_superadmin_null_org_user_requires_null_caller_org(): void
    {
        $useCase = new GetUserByIdUseCase($this->seedUser(orgId: null));

        // A null-org (superadmin) record is only visible to a null-org caller.
        $user = $useCase->execute(1, null);
        self::assertSame(1, $user->id);

        $this->expectException(UserNotFoundException::class);
        $useCase->execute(1, 7);
    }
}
