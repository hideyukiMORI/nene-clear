<?php

declare(strict_types=1);

namespace NeneClear\Tests\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use NeneClear\Auth\Capability;
use NeneClear\Auth\CapabilityMiddleware;
use NeneClear\Auth\CapabilityRule;
use NeneClear\Auth\Role;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\I18n\MessageCatalog;
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

final class CapabilityMiddlewareTest extends TestCase
{
    private Psr17Factory $psr17;
    private CapabilityMiddleware $middleware;
    private SpyRequestHandler $next;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $problemDetails = new LocalizedProblemDetailsFactory(
            new MessageCatalog(dirname(__DIR__, 2) . '/lang'),
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-clear.dev/problems/'),
        );

        $this->middleware = new CapabilityMiddleware($problemDetails, [
            '/admin/organizations' => CapabilityRule::same(Capability::ManageOrganizations),
            '/admin/users' => CapabilityRule::same(Capability::ManageUsers),
            '/admin/reconciliations' => new CapabilityRule(
                read: Capability::ViewReconciliation,
                write: Capability::ManageReconciliation,
            ),
        ]);

        $this->next = new SpyRequestHandler();
    }

    private function request(string $method, string $path, ?Role $role): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest($method, $path);
        if ($role !== null) {
            $request = $request->withAttribute('nene2.auth.claims', ['role' => $role->value]);
        }

        return $request;
    }

    public function test_unmatched_path_passes_through_without_capability_check(): void
    {
        $response = $this->middleware->process(
            $this->request('GET', '/admin/dashboard', Role::Viewer),
            $this->next,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->next->called);
    }

    public function test_admin_can_write_users(): void
    {
        $response = $this->middleware->process(
            $this->request('POST', '/admin/users', Role::Admin),
            $this->next,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->next->called);
    }

    public function test_viewer_cannot_write_users(): void
    {
        $response = $this->middleware->process(
            $this->request('POST', '/admin/users', Role::Viewer),
            $this->next,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($this->next->called);
    }

    public function test_viewer_can_read_reconciliations(): void
    {
        $response = $this->middleware->process(
            $this->request('GET', '/admin/reconciliations', Role::Viewer),
            $this->next,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_viewer_cannot_write_reconciliations(): void
    {
        $response = $this->middleware->process(
            $this->request('POST', '/admin/reconciliations', Role::Viewer),
            $this->next,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($this->next->called);
    }

    public function test_member_can_write_reconciliations(): void
    {
        $response = $this->middleware->process(
            $this->request('POST', '/admin/reconciliations', Role::Member),
            $this->next,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_member_cannot_manage_organizations(): void
    {
        $response = $this->middleware->process(
            $this->request('GET', '/admin/organizations', Role::Member),
            $this->next,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_superadmin_can_manage_organizations(): void
    {
        $response = $this->middleware->process(
            $this->request('POST', '/admin/organizations', Role::Superadmin),
            $this->next,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_missing_claims_is_rejected_on_protected_path(): void
    {
        $response = $this->middleware->process(
            $this->request('POST', '/admin/users', null),
            $this->next,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($this->next->called);
    }

    public function test_unknown_role_value_is_rejected(): void
    {
        $request = $this->psr17->createServerRequest('POST', '/admin/users')
            ->withAttribute('nene2.auth.claims', ['role' => 'wizard']);

        $response = $this->middleware->process($request, $this->next);

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_head_is_treated_as_safe_method(): void
    {
        // HEAD on a write-gated path uses the read capability → viewer allowed.
        $response = $this->middleware->process(
            $this->request('HEAD', '/admin/reconciliations', Role::Viewer),
            $this->next,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_403_response_is_problem_json(): void
    {
        $response = $this->middleware->process(
            $this->request('POST', '/admin/users', Role::Viewer),
            $this->next,
        );

        self::assertStringContainsString('application/problem+json', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
        self::assertStringContainsString('insufficient-capability', $body);
    }
}
