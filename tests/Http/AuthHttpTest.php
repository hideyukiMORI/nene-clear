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

final class AuthHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';

    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-auth-', true) . '.sqlite';
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

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $path, array $body): ResponseInterface
    {
        $request = $this->psr17->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($body)));

        return $this->app->handle($request);
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

    public function test_login_succeeds_with_correct_credentials(): void
    {
        $response = $this->postJson('/admin/auth/login', ['email' => 'admin@acme.example', 'password' => self::PASSWORD]);

        self::assertSame(200, $response->getStatusCode());
        $data = $this->decode($response);
        self::assertArrayHasKey('token', $data);
        self::assertIsString($data['token']);
        self::assertNotSame('', $data['token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/admin/auth/login', ['email' => 'admin@acme.example', 'password' => 'nope']);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function test_login_locks_out_after_repeated_failures(): void
    {
        // 5 failures from the same identifier (email + IP) engage the lock.
        for ($i = 0; $i < 5; $i++) {
            $r = $this->postJson('/admin/auth/login', ['email' => 'admin@acme.example', 'password' => 'nope']);
            self::assertSame(401, $r->getStatusCode(), "attempt $i");
        }

        // 6th request is throttled with 429, even with the CORRECT password.
        $locked = $this->postJson('/admin/auth/login', ['email' => 'admin@acme.example', 'password' => self::PASSWORD]);
        self::assertSame(429, $locked->getStatusCode());
        $body = $this->decode($locked);
        self::assertStringContainsString('too-many-login-attempts', (string) $body['type']);
        self::assertArrayHasKey('retry_after_seconds', $body);
    }

    public function test_successful_login_resets_the_throttle(): void
    {
        // 4 failures (below the threshold) then a success clears the counter.
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/admin/auth/login', ['email' => 'admin@acme.example', 'password' => 'nope']);
        }
        $ok = $this->postJson('/admin/auth/login', ['email' => 'admin@acme.example', 'password' => self::PASSWORD]);
        self::assertSame(200, $ok->getStatusCode());

        // After the reset, 4 more failures still do not lock.
        for ($i = 0; $i < 4; $i++) {
            $r = $this->postJson('/admin/auth/login', ['email' => 'admin@acme.example', 'password' => 'nope']);
            self::assertSame(401, $r->getStatusCode());
        }
    }

    public function test_me_returns_user_with_valid_token(): void
    {
        $token = $this->decode($this->postJson('/admin/auth/login', [
            'email' => 'admin@acme.example',
            'password' => self::PASSWORD,
        ]))['token'];
        self::assertIsString($token);

        $request = $this->psr17->createServerRequest('GET', '/admin/auth/me')
            ->withHeader('Authorization', 'Bearer ' . $token);
        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $data = $this->decode($response);
        self::assertSame('admin@acme.example', $data['email'] ?? null);
        self::assertSame('admin', $data['role'] ?? null);
        self::assertSame(7, $data['organization_id'] ?? null);
        // Org fiscal year-end month is surfaced (null until configured).
        self::assertArrayHasKey('fiscal_year_end_month', $data);
        self::assertNull($data['fiscal_year_end_month']);
    }

    public function test_me_includes_configured_fiscal_year_end_month(): void
    {
        (new \NeneClear\ClearSettings\PdoClearSettingsRepository(
            $this->query,
            new \NeneClear\BankImport\PdoBankAccountRepository($this->query),
        ))->save(new \NeneClear\ClearSettings\ClearSettings(
            organizationId: 7,
            upstreamBaseUrl: '',
            upstreamTokenRef: '',
            dunningMinIntervalDays: 7,
            fiscalYearEndMonth: 3,
        ));

        $token = $this->decode($this->postJson('/admin/auth/login', [
            'email' => 'admin@acme.example',
            'password' => self::PASSWORD,
        ]))['token'];
        self::assertIsString($token);

        $response = $this->app->handle(
            $this->psr17->createServerRequest('GET', '/admin/auth/me')->withHeader('Authorization', 'Bearer ' . $token),
        );
        self::assertSame(3, $this->decode($response)['fiscal_year_end_month'] ?? null);
    }

    public function test_me_requires_a_token(): void
    {
        $response = $this->app->handle($this->psr17->createServerRequest('GET', '/admin/auth/me'));

        self::assertSame(401, $response->getStatusCode());
    }
}
