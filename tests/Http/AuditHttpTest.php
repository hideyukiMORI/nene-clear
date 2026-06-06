<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

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

final class AuditHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-audit-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $query = $kit->queryExecutor;
        SchemaFixture::createUsers($query);
        SchemaFixture::createLoginAttempts($query);
        SchemaFixture::createAuditEvents($query);

        $users = new PdoUserRepository($query);
        $users->save($this->user('admin@acme.example', Role::Admin, 7));
        $users->save($this->user('member@acme.example', Role::Member, 7));

        $this->app = ApplicationFactory::create(query: $query, transactionManager: $kit->transactionManager, jwtSecret: self::SECRET);
        $this->psr17 = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function user(string $email, Role $role, int $org): User
    {
        return new User(
            email: $email,
            role: $role,
            status: UserStatus::Active,
            passwordHash: password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            organizationId: $org,
        );
    }

    private function tokenFor(string $email): string
    {
        $request = $this->psr17->createServerRequest('POST', '/admin/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode(['email' => $email, 'password' => self::PASSWORD])));
        $token = $this->decode($this->app->handle($request))['token'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function request(string $method, string $path, string $token, ?array $json = null): ResponseInterface
    {
        $request = $this->psr17->createServerRequest($method, $path)->withHeader('Authorization', 'Bearer ' . $token);
        if ($json !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->psr17->createStream((string) json_encode($json)));
        }

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

    public function test_admin_sees_mutations_with_before_and_after(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        // A mutating operation that must leave an audit trail.
        $created = $this->request('POST', '/admin/users', $token, [
            'email' => 'new@acme.example',
            'role' => 'member',
            'password' => 'a-strong-password',
        ]);
        self::assertSame(201, $created->getStatusCode());

        $list = $this->request('GET', '/admin/audit-events', $token);
        self::assertSame(200, $list->getStatusCode());
        $body = $this->decode($list);

        self::assertArrayHasKey('items', $body);
        $types = array_column($body['items'], 'event_type');
        self::assertContains('user_created', $types);
        self::assertContains('login_succeeded', $types);

        $userCreated = null;
        foreach ($body['items'] as $item) {
            if ($item['event_type'] === 'user_created') {
                $userCreated = $item;
                break;
            }
        }
        self::assertNotNull($userCreated);
        self::assertSame('new@acme.example', $userCreated['payload']['after']['email']);
        // No password material ever reaches the audit trail.
        self::assertStringNotContainsStringIgnoringCase('a-strong-password', (string) json_encode($userCreated));
    }

    public function test_event_type_filter_narrows_results(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $this->request('POST', '/admin/users', $token, [
            'email' => 'new@acme.example',
            'role' => 'member',
            'password' => 'a-strong-password',
        ]);

        $body = $this->decode($this->request('GET', '/admin/audit-events?event_type=user_created', $token));
        $types = array_unique(array_column($body['items'], 'event_type'));
        self::assertSame(['user_created'], array_values($types));
    }

    public function test_member_lacks_capability_to_read_audit_trail(): void
    {
        $token = $this->tokenFor('member@acme.example');

        $response = $this->request('GET', '/admin/audit-events', $token);
        self::assertSame(403, $response->getStatusCode());
    }
}
