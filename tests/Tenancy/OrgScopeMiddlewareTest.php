<?php

declare(strict_types=1);

namespace NeneClear\Tests\Tenancy;

use Nene2\Http\RequestScopedHolder;
use NeneClear\Tenancy\OrgScopeMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Records whether the downstream handler was reached.
 */
final class SpyRequestHandler implements RequestHandlerInterface
{
    public bool $called = false;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->called = true;

        return (new Psr17Factory())->createResponse(200);
    }
}

/**
 * Pins the tenant-resolution middleware (#300/#301): the organization scope is
 * populated from the verified `org` claim, a header cannot forge it, and a
 * cross-tenant superadmin (`org` absent) leaves the holder unset so downstream
 * org-scoped code fails closed rather than scoping to org 0.
 */
final class OrgScopeMiddlewareTest extends TestCase
{
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    /**
     * @param RequestScopedHolder<int> $holder
     */
    private function process(RequestScopedHolder $holder, ServerRequestInterface $request): SpyRequestHandler
    {
        $next = new SpyRequestHandler();
        (new OrgScopeMiddleware($holder))->process($request, $next);

        return $next;
    }

    public function test_org_claim_populates_the_holder(): void
    {
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $request = $this->psr17->createServerRequest('GET', '/admin/reconciliations')
            ->withAttribute('nene2.auth.claims', ['org' => 42]);

        $next = $this->process($holder, $request);

        self::assertTrue($holder->isSet());
        self::assertSame(42, $holder->get());
        self::assertTrue($next->called);
    }

    public function test_superadmin_without_org_claim_leaves_the_holder_unset(): void
    {
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $request = $this->psr17->createServerRequest('GET', '/admin/reconciliations')
            ->withAttribute('nene2.auth.claims', ['role' => 'admin']);

        $next = $this->process($holder, $request);

        // Fail-close is deferred to the read side (HolderCurrentOrganization):
        // the middleware itself never blocks, it simply does not scope.
        self::assertFalse($holder->isSet());
        self::assertTrue($next->called);
    }

    public function test_missing_claims_attribute_leaves_the_holder_unset(): void
    {
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $request = $this->psr17->createServerRequest('GET', '/health');

        $this->process($holder, $request);

        self::assertFalse($holder->isSet());
    }

    public function test_a_header_cannot_forge_the_organization_scope(): void
    {
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        // A spoofed header must be ignored; only the verified claim counts.
        $request = $this->psr17->createServerRequest('GET', '/admin/reconciliations')
            ->withHeader('X-Organization-Id', '999')
            ->withAttribute('nene2.auth.claims', ['org' => 7]);

        $this->process($holder, $request);

        self::assertSame(7, $holder->get());
    }
}
