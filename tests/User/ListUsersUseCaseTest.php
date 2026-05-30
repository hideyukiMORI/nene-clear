<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\Auth\Role;
use NeneClear\User\ListUsersUseCase;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class ListUsersUseCaseTest extends TestCase
{
    private function user(int $id, int $orgId, string $email): User
    {
        return new User(
            email: $email,
            role: Role::Member,
            status: UserStatus::Active,
            passwordHash: 'h',
            organizationId: $orgId,
            id: $id,
        );
    }

    public function test_lists_only_callers_organization(): void
    {
        $repo = new InMemoryUserRepository([
            $this->user(1, 7, 'a@x.example'),
            $this->user(2, 7, 'b@x.example'),
            $this->user(3, 999, 'c@y.example'),
        ]);
        $useCase = new ListUsersUseCase($repo);

        $output = $useCase->execute(7, 50, 0);

        self::assertCount(2, $output->items);
        self::assertSame(2, $output->total);
        self::assertSame(50, $output->limit);
        self::assertSame(0, $output->offset);
    }

    public function test_pagination_limit_and_offset(): void
    {
        $repo = new InMemoryUserRepository([
            $this->user(1, 7, 'a@x.example'),
            $this->user(2, 7, 'b@x.example'),
            $this->user(3, 7, 'c@x.example'),
        ]);
        $useCase = new ListUsersUseCase($repo);

        $page = $useCase->execute(7, 2, 0);
        self::assertCount(2, $page->items);
        self::assertSame(3, $page->total); // total ignores pagination

        $next = $useCase->execute(7, 2, 2);
        self::assertCount(1, $next->items);
        self::assertSame(3, $next->total);
    }

    public function test_empty_organization_returns_empty(): void
    {
        $useCase = new ListUsersUseCase(new InMemoryUserRepository());

        $output = $useCase->execute(7, 50, 0);

        self::assertSame([], $output->items);
        self::assertSame(0, $output->total);
    }

    public function test_offset_beyond_total_returns_empty_but_keeps_total(): void
    {
        $repo = new InMemoryUserRepository([$this->user(1, 7, 'a@x.example')]);
        $useCase = new ListUsersUseCase($repo);

        $output = $useCase->execute(7, 50, 100);

        self::assertSame([], $output->items);
        self::assertSame(1, $output->total);
    }
}
