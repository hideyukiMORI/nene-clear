<?php

declare(strict_types=1);

namespace NeneClear\Tests\Organization;

use NeneClear\Organization\ListOrganizationsUseCase;
use NeneClear\Organization\Organization;
use PHPUnit\Framework\TestCase;

final class ListOrganizationsUseCaseTest extends TestCase
{
    public function test_lists_all_with_total(): void
    {
        $repo = new InMemoryOrganizationRepository([
            new Organization(slug: 'a', name: 'A'),
            new Organization(slug: 'b', name: 'B'),
        ]);
        $useCase = new ListOrganizationsUseCase($repo);

        $output = $useCase->execute(50, 0);

        self::assertCount(2, $output->items);
        self::assertSame(2, $output->total);
        self::assertSame(50, $output->limit);
        self::assertSame(0, $output->offset);
    }

    public function test_pagination(): void
    {
        $repo = new InMemoryOrganizationRepository([
            new Organization(slug: 'a', name: 'A'),
            new Organization(slug: 'b', name: 'B'),
            new Organization(slug: 'c', name: 'C'),
        ]);
        $useCase = new ListOrganizationsUseCase($repo);

        $page = $useCase->execute(2, 0);
        self::assertCount(2, $page->items);
        self::assertSame(3, $page->total);

        $next = $useCase->execute(2, 2);
        self::assertCount(1, $next->items);
        self::assertSame(3, $next->total);
    }

    public function test_empty_returns_empty(): void
    {
        $useCase = new ListOrganizationsUseCase(new InMemoryOrganizationRepository());

        $output = $useCase->execute(50, 0);

        self::assertSame([], $output->items);
        self::assertSame(0, $output->total);
    }
}
