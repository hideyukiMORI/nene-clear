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

final class OrganizationCrudHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';

    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-orgcrud-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createOrganizations($this->query);
        SchemaFixture::createUsers($this->query);
        SchemaFixture::createLoginAttempts($this->query);
        SchemaFixture::createAuditEvents($this->query);

        $users = new PdoUserRepository($this->query);
        $users->save(new User(
            email: 'root@platform.example',
            role: Role::Superadmin,
            status: UserStatus::Active,
            passwordHash: password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            organizationId: null,
        ));
        $users->save(new User(
            email: 'admin@acme.example',
            role: Role::Admin,
            status: UserStatus::Active,
            passwordHash: password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            organizationId: 1,
        ));

        $this->app = ApplicationFactory::create(query: $this->query, jwtSecret: self::SECRET);
        $this->psr17 = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function tokenFor(string $email): string
    {
        $request = $this->psr17->createServerRequest('POST', '/admin/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode([
                'email' => $email,
                'password' => self::PASSWORD,
            ])));

        $data = $this->decode($this->app->handle($request));
        self::assertIsString($data['token'] ?? null);

        return (string) $data['token'];
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function request(string $method, string $path, string $token, ?array $json = null): ResponseInterface
    {
        $request = $this->psr17->createServerRequest($method, $path)
            ->withHeader('Authorization', 'Bearer ' . $token);

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

    public function test_superadmin_can_create_list_get_and_delete(): void
    {
        $token = $this->tokenFor('root@platform.example');

        $created = $this->request('POST', '/admin/organizations', $token, ['slug' => 'acme', 'name' => 'Acme Co']);
        self::assertSame(201, $created->getStatusCode());
        $id = $this->decode($created)['organization_id'] ?? null;
        self::assertIsInt($id);

        $list = $this->request('GET', '/admin/organizations', $token);
        self::assertSame(200, $list->getStatusCode());
        self::assertSame(1, $this->decode($list)['total'] ?? null);

        $get = $this->request('GET', '/admin/organizations/' . $id, $token);
        self::assertSame(200, $get->getStatusCode());
        self::assertSame('acme', $this->decode($get)['slug'] ?? null);

        $deleted = $this->request('DELETE', '/admin/organizations/' . $id, $token);
        self::assertSame(204, $deleted->getStatusCode());

        $missing = $this->request('GET', '/admin/organizations/' . $id, $token);
        self::assertSame(404, $missing->getStatusCode());
    }

    public function test_admin_lacks_manage_organizations_capability(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $response = $this->request('POST', '/admin/organizations', $token, ['slug' => 'x', 'name' => 'X']);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('insufficient-capability', (string) $response->getBody());
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $token = $this->tokenFor('root@platform.example');
        $this->request('POST', '/admin/organizations', $token, ['slug' => 'dup', 'name' => 'First']);

        $second = $this->request('POST', '/admin/organizations', $token, ['slug' => 'dup', 'name' => 'Second']);
        self::assertSame(409, $second->getStatusCode());
    }
}
