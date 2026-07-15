<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Auth\Role;
use NeneClear\Http\ApplicationFactory;
use NeneClear\Tests\Support\SchemaFixture;
use NeneClear\User\PdoUserRepository;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * End-to-end proof that the opt-in X-Authorization fallback receiver (NENE2 #1558 /
 * ADR 0019) is wired into this product's runtime pipeline via
 * {@see ApplicationFactory}'s `enableAuthorizationHeaderFallback: true`.
 *
 * The admin SPA (and, historically, this repo's own now-deleted
 * `NeneClear\Http\AuthorizationHeaderFallback`) mirrors every bearer token into
 * `X-Authorization: Bearer <token>` so that shared hosting (HETEML-type Tier A,
 * #265) — where an upstream proxy strips the standard `Authorization` header
 * before PHP sees it — can still authenticate. The framework's
 * `AuthorizationHeaderFallbackMiddleware` restores `Authorization` from the
 * mirror (only when `Authorization` is absent/empty) at the head of the auth
 * stage, before the bearer auth middleware runs.
 *
 * `GET /admin/auth/me` is bearer-protected but carries no capability rule
 * (see {@see \NeneClear\Auth\AuthServiceProvider}'s `CapabilityMiddleware`
 * map), so these assertions isolate the credential-restoration behaviour.
 *
 * The tests fail if the opt-in flag is removed from `ApplicationFactory`: a
 * mirror-only request would then never restore `Authorization` and would be
 * rejected as `missing_token`.
 */
final class AuthorizationHeaderFallbackE2ETest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';
    private const string PROTECTED_PATH = '/admin/auth/me';

    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-authz-fallback-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $this->query = $kit->queryExecutor;
        SchemaFixture::createUsers($this->query);
        SchemaFixture::createTotpTables($this->query);
        SchemaFixture::createLoginAttempts($this->query);
        SchemaFixture::createAuditEvents($this->query);
        SchemaFixture::createClearSettings($this->query);

        (new PdoUserRepository($this->query))->save(new User(
            email: 'admin@acme.example',
            role: Role::Admin,
            status: UserStatus::Active,
            passwordHash: password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            organizationId: 7,
        ));

        $this->app = ApplicationFactory::create(query: $this->query, transactionManager: $kit->transactionManager, jwtSecret: self::SECRET);
        $this->psr17 = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function issueToken(): string
    {
        $request = $this->psr17->createServerRequest('POST', '/admin/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode([
                'email' => 'admin@acme.example',
                'password' => self::PASSWORD,
            ])));

        $response = $this->app->handle($request);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);
        self::assertIsString($data['token'] ?? null);

        return $data['token'];
    }

    /**
     * The mirror end-to-end proof: a valid bearer token supplied ONLY in the
     * `X-Authorization` header (no standard `Authorization`) is restored by the
     * fallback receiver and accepted by the bearer auth stage — the request passes
     * authentication and reaches the handler.
     */
    public function test_valid_token_in_mirror_only_passes_authentication(): void
    {
        $token = $this->issueToken();

        $request = $this->psr17->createServerRequest('GET', self::PROTECTED_PATH)
            ->withHeader('X-Authorization', 'Bearer ' . $token);

        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $data = $this->decode($response);
        self::assertSame('admin@acme.example', $data['email'] ?? null);
    }

    /**
     * The auth stage actually receives the mirrored credential: an INVALID token
     * in `X-Authorization` only is rejected as `invalid_token` (the bearer
     * middleware saw a token), NOT `missing_token` — which is only possible if the
     * fallback receiver restored `Authorization` from the mirror before auth ran.
     */
    public function test_invalid_token_in_mirror_only_reaches_bearer_stage_as_invalid_not_missing(): void
    {
        $request = $this->psr17->createServerRequest('GET', self::PROTECTED_PATH)
            ->withHeader('X-Authorization', 'Bearer not-a-real-token');

        $response = $this->app->handle($request);

        self::assertSame(401, $response->getStatusCode());
        $wwwAuth = $response->getHeaderLine('WWW-Authenticate');
        self::assertStringContainsString('error="invalid_token"', $wwwAuth);
        self::assertStringNotContainsString('error="missing_token"', $wwwAuth);
    }

    /**
     * Baseline / control: with NO credential in either header, the auth stage
     * reports `missing_token`. This is the response a mirror-only request would get
     * if the opt-in fallback were disabled.
     */
    public function test_no_credential_yields_missing_token(): void
    {
        $response = $this->app->handle($this->psr17->createServerRequest('GET', self::PROTECTED_PATH));

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString(
            'error="missing_token"',
            $response->getHeaderLine('WWW-Authenticate'),
        );
    }

    /**
     * The standard header still wins when both are present (byte-for-byte behaviour
     * unchanged on hosting that delivers `Authorization`): a valid standard token
     * authenticates even when an invalid mirror is also sent. If the receiver wrongly
     * preferred the mirror, the bearer stage would reject the invalid token with an
     * `invalid_token` challenge; its absence proves standard-header precedence.
     */
    public function test_standard_authorization_header_takes_precedence_over_mirror(): void
    {
        $token = $this->issueToken();

        $request = $this->psr17->createServerRequest('GET', self::PROTECTED_PATH)
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Authorization', 'Bearer not-a-real-token');

        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);

        return $data;
    }
}
