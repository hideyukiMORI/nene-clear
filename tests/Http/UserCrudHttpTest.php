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

final class UserCrudHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;
    private int $crossTenantUserId;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-usercrud-', true) . '.sqlite';
        $query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createUsers($query);
        SchemaFixture::createLoginAttempts($query);
        SchemaFixture::createAuditEvents($query);

        $users = new PdoUserRepository($query);
        $users->save($this->user('admin@acme.example', Role::Admin, 7));
        $users->save($this->user('member@acme.example', Role::Member, 7));
        $this->crossTenantUserId = $users->save($this->user('other@org8.example', Role::Member, 8));

        $this->app = ApplicationFactory::create(query: $query, jwtSecret: self::SECRET);
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

    public function test_admin_can_create_list_get_update_and_delete_in_own_org(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $created = $this->request('POST', '/admin/users', $token, [
            'email' => 'new@acme.example',
            'role' => 'member',
            'password' => 'a-strong-password',
        ]);
        self::assertSame(201, $created->getStatusCode());
        $body = $this->decode($created);
        $id = $body['user_id'] ?? null;
        self::assertIsInt($id);
        self::assertSame(7, $body['organization_id'] ?? null);

        $list = $this->request('GET', '/admin/users', $token);
        self::assertSame(200, $list->getStatusCode());
        self::assertSame(3, $this->decode($list)['total'] ?? null); // admin + member + new (org 7 only)

        $updated = $this->request('PUT', '/admin/users/' . $id, $token, ['role' => 'viewer']);
        self::assertSame(200, $updated->getStatusCode());
        self::assertSame('viewer', $this->decode($updated)['role'] ?? null);

        self::assertSame(204, $this->request('DELETE', '/admin/users/' . $id, $token)->getStatusCode());
        self::assertSame(404, $this->request('GET', '/admin/users/' . $id, $token)->getStatusCode());
    }

    public function test_member_lacks_manage_users_capability(): void
    {
        $token = $this->tokenFor('member@acme.example');

        $response = $this->request('GET', '/admin/users', $token);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('insufficient-capability', (string) $response->getBody());
    }

    public function test_cross_tenant_user_is_not_found(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $response = $this->request('GET', '/admin/users/' . $this->crossTenantUserId, $token);

        self::assertSame(404, $response->getStatusCode());
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $response = $this->request('POST', '/admin/users', $token, [
            'email' => 'admin@acme.example',
            'role' => 'member',
            'password' => 'a-strong-password',
        ]);

        self::assertSame(409, $response->getStatusCode());
    }
}
