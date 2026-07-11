<?php

declare(strict_types=1);

namespace NeneClear\Tests\Tenancy;

use Nene2\Error\ProblemDetailsResponseFactory;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\I18n\MessageCatalog;
use NeneClear\Tenancy\MissingOrganizationScopeException;
use NeneClear\Tenancy\MissingOrganizationScopeExceptionHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Pins the org-less-token mapping (#300/#301): a
 * {@see MissingOrganizationScopeException} renders as a 403
 * `organization-not-resolved` problem (invoice OrgGuard "inconsistent token"
 * semantics), and the handler does not swallow unrelated exceptions.
 */
final class MissingOrganizationScopeExceptionHandlerTest extends TestCase
{
    private MissingOrganizationScopeExceptionHandler $handler;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $problemDetails = new LocalizedProblemDetailsFactory(
            new MessageCatalog(dirname(__DIR__, 2) . '/lang'),
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-clear.dev/problems/'),
        );

        $this->handler = new MissingOrganizationScopeExceptionHandler($problemDetails);
    }

    public function test_supports_only_the_missing_scope_exception(): void
    {
        self::assertTrue($this->handler->supports(new MissingOrganizationScopeException()));
        self::assertFalse($this->handler->supports(new RuntimeException('unrelated')));
    }

    public function test_renders_403_organization_not_resolved(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/admin/reconciliations');

        $response = $this->handler->handle(new MissingOrganizationScopeException(), $request);

        self::assertSame(403, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertStringContainsString('organization-not-resolved', (string) ($body['type'] ?? ''));
        self::assertSame(403, $body['status'] ?? null);
    }
}
