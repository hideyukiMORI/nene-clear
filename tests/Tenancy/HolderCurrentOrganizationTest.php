<?php

declare(strict_types=1);

namespace NeneClear\Tests\Tenancy;

use Nene2\Http\RequestScopedHolder;
use NeneClear\Tenancy\FixedOrganization;
use NeneClear\Tenancy\HolderCurrentOrganization;
use NeneClear\Tenancy\MissingOrganizationScopeException;
use NeneClear\Tenancy\OrgScopeMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

/**
 * Pins the read side of tenant resolution (#300/#301): a handler that receives
 * {@see \NeneClear\Tenancy\CurrentOrganization} gets the scoped id when the
 * middleware ran with an `org` claim, and fails closed
 * ({@see MissingOrganizationScopeException}) when it did not — never a silent
 * org 0.
 */
final class HolderCurrentOrganizationTest extends TestCase
{
    public function test_returns_the_populated_organization_id(): void
    {
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $holder->set(42);

        self::assertSame(42, (new HolderCurrentOrganization($holder))->id());
    }

    public function test_fails_closed_when_the_holder_was_never_populated(): void
    {
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();

        $this->expectException(MissingOrganizationScopeException::class);

        (new HolderCurrentOrganization($holder))->id();
    }

    public function test_end_to_end_org_claim_resolves_to_the_scoped_id(): void
    {
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $request = (new Psr17Factory())->createServerRequest('GET', '/admin/reconciliations')
            ->withAttribute('nene2.auth.claims', ['org' => 7]);

        (new OrgScopeMiddleware($holder))->process($request, new SpyRequestHandler());

        self::assertSame(7, (new HolderCurrentOrganization($holder))->id());
    }

    public function test_end_to_end_superadmin_org_null_fails_closed(): void
    {
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $request = (new Psr17Factory())->createServerRequest('GET', '/admin/reconciliations')
            ->withAttribute('nene2.auth.claims', ['role' => 'admin']);

        (new OrgScopeMiddleware($holder))->process($request, new SpyRequestHandler());

        $this->expectException(MissingOrganizationScopeException::class);

        (new HolderCurrentOrganization($holder))->id();
    }

    public function test_fixed_organization_returns_its_pinned_id(): void
    {
        self::assertSame(99, (new FixedOrganization(99))->id());
    }
}
