<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use Nene2\Auth\TotpAuthenticator as Rfc6238Totp;
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

final class TotpEnrollmentHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;
    private Rfc6238Totp $gen;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-totp-http-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $query = $kit->queryExecutor;

        SchemaFixture::createUsers($query);
        SchemaFixture::createLoginAttempts($query);
        SchemaFixture::createAuditEvents($query);
        SchemaFixture::createTotpTables($query);

        (new PdoUserRepository($query))->save(new User(
            email: 'admin@acme.example',
            role: Role::Admin,
            status: UserStatus::Active,
            passwordHash: password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            organizationId: 7,
        ));

        $this->app = ApplicationFactory::create(query: $query, transactionManager: $kit->transactionManager, jwtSecret: self::SECRET);
        $this->psr17 = new Psr17Factory();
        $this->gen = new Rfc6238Totp();
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function token(): string
    {
        $request = $this->psr17->createServerRequest('POST', '/admin/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode(['email' => 'admin@acme.example', 'password' => self::PASSWORD])));
        $token = $this->decode($this->app->handle($request))['token'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private function login(string $email, string $password): ResponseInterface
    {
        $request = $this->psr17->createServerRequest('POST', '/admin/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode(['email' => $email, 'password' => $password])));

        return $this->app->handle($request);
    }

    private function loginMfa(string $mfaToken, string $code): ResponseInterface
    {
        $request = $this->psr17->createServerRequest('POST', '/admin/auth/login/mfa')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode(['mfa_token' => $mfaToken, 'code' => $code])));

        return $this->app->handle($request);
    }

    /** @param array<string, mixed> $body */
    private function req(string $method, string $path, string $token, array $body = []): ResponseInterface
    {
        $request = $this->psr17->createServerRequest($method, $path)->withHeader('Authorization', 'Bearer ' . $token);
        if ($body !== []) {
            $request = $request->withHeader('Content-Type', 'application/json')->withParsedBody($body);
        }

        return $this->app->handle($request);
    }

    /** @return array<string, mixed> */
    private function decode(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);

        return $data;
    }

    public function test_full_enrolment_lifecycle(): void
    {
        $token = $this->token();

        $setup = $this->req('POST', '/admin/auth/totp/setup', $token);
        self::assertSame(200, $setup->getStatusCode());
        $secret = (string) $this->decode($setup)['secret'];
        self::assertNotSame('', $secret);
        self::assertStringContainsString('otpauth://totp/', (string) $this->decode($setup)['otpauth_uri']);

        self::assertFalse($this->decode($this->req('GET', '/admin/auth/totp', $token))['enabled']);

        $enable = $this->req('POST', '/admin/auth/totp/enable', $token, ['code' => $this->gen->computeCode($secret, $this->gen->currentTimeStep())]);
        self::assertSame(200, $enable->getStatusCode());
        $recovery = $this->decode($enable)['recovery_codes'];
        self::assertIsArray($recovery);
        self::assertCount(10, $recovery);

        $status = $this->decode($this->req('GET', '/admin/auth/totp', $token));
        self::assertTrue($status['enabled']);
        self::assertSame(10, $status['recovery_codes_remaining']);

        // Re-enabling is rejected (409) — the is-enabled check fires before any code check.
        self::assertSame(409, $this->req('POST', '/admin/auth/totp/enable', $token, ['code' => $this->gen->computeCode($secret, $this->gen->currentTimeStep())])->getStatusCode());

        // Disable with a recovery code, then the enrolment is gone.
        self::assertSame(200, $this->req('DELETE', '/admin/auth/totp', $token, ['code' => (string) $recovery[0]])->getStatusCode());
        self::assertFalse($this->decode($this->req('GET', '/admin/auth/totp', $token))['enabled']);
    }

    public function test_enable_with_a_wrong_code_returns_401(): void
    {
        $token = $this->token();
        $this->req('POST', '/admin/auth/totp/setup', $token);

        self::assertSame(401, $this->req('POST', '/admin/auth/totp/enable', $token, ['code' => 'xxxxxx'])->getStatusCode());
    }

    public function test_totp_routes_require_authentication(): void
    {
        self::assertSame(401, $this->req('GET', '/admin/auth/totp', 'not-a-valid-token')->getStatusCode());
    }

    public function test_login_requires_a_second_factor_after_enrolment(): void
    {
        // Enrol first — the plain token() works because TOTP is not enabled yet.
        $token = $this->token();
        $secret = (string) $this->decode($this->req('POST', '/admin/auth/totp/setup', $token))['secret'];
        $this->req('POST', '/admin/auth/totp/enable', $token, ['code' => $this->gen->computeCode($secret, $this->gen->currentTimeStep())]);

        // A correct password now yields a challenge, not a session token, and leaks no user details.
        $challenge = $this->decode($this->login('admin@acme.example', self::PASSWORD));
        self::assertTrue($challenge['mfa_required'] ?? false);
        self::assertArrayHasKey('mfa_token', $challenge);
        self::assertArrayNotHasKey('token', $challenge);
        $mfaToken = (string) $challenge['mfa_token'];

        // Wrong second factor → 401.
        self::assertSame(401, $this->loginMfa($mfaToken, 'xxxxxx')->getStatusCode());

        // Correct second factor (a fresh, unused step) → a usable session token.
        $verify = $this->loginMfa($mfaToken, $this->gen->computeCode($secret, $this->gen->currentTimeStep() + 1));
        self::assertSame(200, $verify->getStatusCode());
        $session = (string) $this->decode($verify)['token'];
        self::assertNotSame('', $session);
        self::assertSame(200, $this->req('GET', '/admin/auth/totp', $session)->getStatusCode());

        // A challenge token can never itself be used as a session bearer token.
        self::assertSame(401, $this->req('GET', '/admin/auth/totp', $mfaToken)->getStatusCode());

        // A garbage challenge token → 401.
        self::assertSame(401, $this->loginMfa('not-a-real-token', '123456')->getStatusCode());
    }
}
