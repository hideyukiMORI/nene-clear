<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\Auth\Role;
use NeneClear\User\DeleteUserUseCase;
use NeneClear\User\User;
use NeneClear\User\UserNotFoundException;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class DeleteUserUseCaseTest extends TestCase
{
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
        $useCase = new DeleteUserUseCase($repo);

        $useCase->execute(1, 7);

        self::assertNull($repo->findById(1));
    }

    public function test_cross_tenant_delete_throws_not_found_and_keeps_user(): void
    {
        $repo = $this->seedUser(orgId: 7);
        $useCase = new DeleteUserUseCase($repo);

        try {
            $useCase->execute(1, 999);
            self::fail('Expected UserNotFoundException');
        } catch (UserNotFoundException) {
            // User must still exist — a foreign tenant cannot delete it.
            self::assertNotNull($repo->findById(1));
        }
    }

    public function test_unknown_user_throws_not_found(): void
    {
        $repo = new InMemoryUserRepository();
        $useCase = new DeleteUserUseCase($repo);

        $this->expectException(UserNotFoundException::class);

        $useCase->execute(9999, 7);
    }
}
