<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use Nene2\Testing\DatabaseTestKit;
use NeneClear\Auth\Role;
use NeneClear\Http\ApplicationFactory;
use NeneClear\Tests\Support\SchemaFixture;
use NeneClear\Tests\User\RecordingInvitationMailer;
use NeneClear\User\PdoUserInvitationRepository;
use NeneClear\User\PdoUserRepository;
use NeneClear\User\User;
use NeneClear\User\UserInvitation;
use NeneClear\User\UserStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class InvitationHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string ADMIN_PASSWORD = 'correct horse battery';

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;
    private RecordingInvitationMailer $mailer;
    private \Nene2\Database\DatabaseQueryExecutorInterface $query;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-invite-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $this->query = $kit->queryExecutor;

        SchemaFixture::createUsers($this->query);

        SchemaFixture::createTotpTables($this->query);
        SchemaFixture::createLoginAttempts($this->query);
        SchemaFixture::createUserInvitations($this->query);
        SchemaFixture::createAuditEvents($this->query);

        $users = new PdoUserRepository($this->query);
        $users->save(new User(
            email: 'admin@acme.example',
            role: Role::Admin,
            status: UserStatus::Active,
            passwordHash: password_hash(self::ADMIN_PASSWORD, PASSWORD_BCRYPT),
            organizationId: 7,
        ));

        $this->mailer = new RecordingInvitationMailer();
        $this->app = ApplicationFactory::create(
            query: $this->query,
            transactionManager: $kit->transactionManager,
            jwtSecret: self::SECRET,
            appBaseUrl: 'https://app.example',
            invitationMailer: $this->mailer,
        );
        $this->psr17 = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function token(string $email, string $password): string
    {
        $req = $this->psr17->createServerRequest('POST', '/admin/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode(['email' => $email, 'password' => $password])));
        $body = $this->decode($this->app->handle($req));
        self::assertIsString($body['token'] ?? null);

        return $body['token'];
    }

    private function get(string $path, ?string $token = null): ResponseInterface
    {
        $req = $this->psr17->createServerRequest('GET', $path);
        if ($token !== null) {
            $req = $req->withHeader('Authorization', 'Bearer ' . $token);
        }

        return $this->app->handle($req);
    }

    /** @param array<string, mixed> $body */
    private function postJson(string $path, array $body, ?string $token = null): ResponseInterface
    {
        $req = $this->psr17->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($body)));
        if ($token !== null) {
            $req = $req->withHeader('Authorization', 'Bearer ' . $token);
        }

        return $this->app->handle($req);
    }

    /** @return array<string, mixed> */
    private function decode(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);

        return $data;
    }

    private function tokenFromMail(): string
    {
        self::assertCount(1, $this->mailer->sent);
        $url = $this->mailer->sent[0]->acceptUrl;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        self::assertIsString($q['token'] ?? null);

        return $q['token'];
    }

    public function test_invite_email_accept_then_login(): void
    {
        $adminToken = $this->token('admin@acme.example', self::ADMIN_PASSWORD);

        // 1. Invite (no password) → invited user + e-mail with a token.
        $created = $this->postJson('/admin/users', ['email' => 'newbie@acme.example', 'role' => 'member'], $adminToken);
        self::assertSame(201, $created->getStatusCode());
        self::assertSame('invited', $this->decode($created)['status'] ?? null);

        $rawToken = $this->tokenFromMail();

        // 2. The invitee (no session) validates the token and sees their e-mail.
        $info = $this->get('/admin/auth/invitation?token=' . $rawToken);
        self::assertSame(200, $info->getStatusCode());
        self::assertSame('newbie@acme.example', $this->decode($info)['email'] ?? null);

        // 3. Cannot log in before accepting (account still invited).
        $earlyLogin = $this->postJson('/admin/auth/login', ['email' => 'newbie@acme.example', 'password' => 'My-new-pass-1']);
        self::assertSame(401, $earlyLogin->getStatusCode());

        // 4. Accept the invite → set password, activate.
        $accept = $this->postJson('/admin/auth/invitation/accept', ['token' => $rawToken, 'password' => 'My-new-pass-1']);
        self::assertSame(200, $accept->getStatusCode());
        self::assertSame('active', $this->decode($accept)['status'] ?? null);

        // 5. Now login works.
        $login = $this->postJson('/admin/auth/login', ['email' => 'newbie@acme.example', 'password' => 'My-new-pass-1']);
        self::assertSame(200, $login->getStatusCode());
        self::assertIsString($this->decode($login)['token'] ?? null);
    }

    public function test_token_is_single_use(): void
    {
        $adminToken = $this->token('admin@acme.example', self::ADMIN_PASSWORD);
        $this->postJson('/admin/users', ['email' => 'newbie@acme.example', 'role' => 'member'], $adminToken);
        $rawToken = $this->tokenFromMail();

        self::assertSame(200, $this->postJson('/admin/auth/invitation/accept', ['token' => $rawToken, 'password' => 'My-new-pass-1'])->getStatusCode());
        // Second use is rejected as invalid (already accepted).
        self::assertSame(404, $this->postJson('/admin/auth/invitation/accept', ['token' => $rawToken, 'password' => 'My-new-pass-2'])->getStatusCode());
    }

    public function test_unknown_token_is_invalid(): void
    {
        $res = $this->get('/admin/auth/invitation?token=deadbeef');
        self::assertSame(404, $res->getStatusCode());
        self::assertStringContainsString('invitation-invalid', (string) $res->getBody());
    }

    public function test_expired_token_is_rejected(): void
    {
        // Seed an already-expired invitation directly.
        $users = new PdoUserRepository($this->query);
        $userId = $users->save(new User(
            email: 'stale@acme.example',
            role: Role::Viewer,
            status: UserStatus::Invited,
            passwordHash: password_hash('x', PASSWORD_BCRYPT),
            organizationId: 7,
        ));
        $raw = 'expired-raw-token';
        (new PdoUserInvitationRepository($this->query))->save(new UserInvitation(
            organizationId: 7,
            userId: $userId,
            tokenHash: hash('sha256', $raw),
            expiresAt: '2000-01-01 00:00:00',
        ));

        $res = $this->get('/admin/auth/invitation?token=' . $raw);
        self::assertSame(410, $res->getStatusCode());
        self::assertStringContainsString('invitation-expired', (string) $res->getBody());
    }

    public function test_short_password_is_rejected(): void
    {
        $adminToken = $this->token('admin@acme.example', self::ADMIN_PASSWORD);
        $this->postJson('/admin/users', ['email' => 'newbie@acme.example', 'role' => 'member'], $adminToken);
        $rawToken = $this->tokenFromMail();

        $res = $this->postJson('/admin/auth/invitation/accept', ['token' => $rawToken, 'password' => 'short']);
        self::assertSame(422, $res->getStatusCode());
    }
}
