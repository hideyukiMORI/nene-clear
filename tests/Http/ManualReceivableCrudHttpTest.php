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

final class ManualReceivableCrudHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-mrcrud-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $query = $kit->queryExecutor;
        SchemaFixture::createUsers($query);
        SchemaFixture::createLoginAttempts($query);
        SchemaFixture::createAuditEvents($query);
        SchemaFixture::createManualReceivables($query);

        $users = new PdoUserRepository($query);
        $users->save($this->user('admin@acme.example', Role::Admin, 7));
        $users->save($this->user('member@acme.example', Role::Member, 7));
        $users->save($this->user('viewer@acme.example', Role::Viewer, 7));
        $users->save($this->user('member@org8.example', Role::Member, 8));

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

    /**
     * @return array<string, mixed>
     */
    private function validBody(string $reference = 'INV-2026-001'): array
    {
        return [
            'reference_number' => $reference,
            'client_name' => 'カ）アクメ',
            'recipient_email' => 'ar@acme.example',
            'total_cents' => 110000,
            'due_at' => '2026-04-30',
        ];
    }

    public function test_member_can_create_list_get_update_and_cancel(): void
    {
        $token = $this->tokenFor('member@acme.example');

        $created = $this->request('POST', '/admin/manual-receivables', $token, $this->validBody());
        self::assertSame(201, $created->getStatusCode());
        $body = $this->decode($created);
        $id = $body['manual_receivable_id'] ?? null;
        self::assertIsInt($id);
        self::assertSame('manual', $body['source'] ?? null);
        self::assertSame('open', $body['status'] ?? null);
        self::assertSame(110000, $body['total_cents'] ?? null);
        self::assertSame(110000, $body['outstanding_cents'] ?? null);
        self::assertSame(7, $body['organization_id'] ?? null);

        $list = $this->request('GET', '/admin/manual-receivables', $token);
        self::assertSame(200, $list->getStatusCode());
        self::assertSame(1, $this->decode($list)['total'] ?? null);

        $got = $this->request('GET', '/admin/manual-receivables/' . $id, $token);
        self::assertSame(200, $got->getStatusCode());
        self::assertSame('INV-2026-001', $this->decode($got)['reference_number'] ?? null);

        $updated = $this->request('PUT', '/admin/manual-receivables/' . $id, $token, [
            'reference_number' => 'INV-2026-001',
            'client_name' => 'カ）アクメ（改）',
            'total_cents' => 90000,
            'due_at' => '2026-05-31',
        ]);
        self::assertSame(200, $updated->getStatusCode());
        $u = $this->decode($updated);
        self::assertSame('カ）アクメ（改）', $u['client_name'] ?? null);
        self::assertSame(90000, $u['total_cents'] ?? null);
        self::assertSame(90000, $u['outstanding_cents'] ?? null);
        // PUT is a full replace: omitting recipient_email clears it.
        self::assertArrayHasKey('recipient_email', $u);
        self::assertNull($u['recipient_email']);

        $cancelled = $this->request('POST', '/admin/manual-receivables/' . $id . '/cancel', $token);
        self::assertSame(200, $cancelled->getStatusCode());
        self::assertSame('cancelled', $this->decode($cancelled)['status'] ?? null);

        // A cancelled receivable can no longer be edited or cancelled again.
        self::assertSame(409, $this->request('PUT', '/admin/manual-receivables/' . $id, $token, $this->validBody())->getStatusCode());
        self::assertSame(409, $this->request('POST', '/admin/manual-receivables/' . $id . '/cancel', $token)->getStatusCode());
    }

    public function test_duplicate_reference_number_is_rejected(): void
    {
        $token = $this->tokenFor('member@acme.example');
        self::assertSame(201, $this->request('POST', '/admin/manual-receivables', $token, $this->validBody('DUP-1'))->getStatusCode());

        $second = $this->request('POST', '/admin/manual-receivables', $token, $this->validBody('DUP-1'));
        self::assertSame(409, $second->getStatusCode());
        self::assertStringContainsString('manual-receivable-already-exists', (string) $this->decode($second)['type']);
    }

    public function test_validation_rejects_missing_and_bad_fields(): void
    {
        $token = $this->tokenFor('member@acme.example');

        self::assertSame(422, $this->request('POST', '/admin/manual-receivables', $token, [
            'reference_number' => '',
            'client_name' => '',
            'total_cents' => 0,
        ])->getStatusCode());

        self::assertSame(422, $this->request('POST', '/admin/manual-receivables', $token, [
            'reference_number' => 'X',
            'client_name' => 'Y',
            'total_cents' => 1000,
            'due_at' => '2026-13-40',
            'recipient_email' => 'not-an-email',
        ])->getStatusCode());
    }

    public function test_tenant_isolation(): void
    {
        $org8 = $this->tokenFor('member@org8.example');
        $org8Id = $this->decode($this->request('POST', '/admin/manual-receivables', $org8, $this->validBody('ORG8-1')))['manual_receivable_id'];

        $org7 = $this->tokenFor('member@acme.example');
        self::assertSame(404, $this->request('GET', '/admin/manual-receivables/' . $org8Id, $org7)->getStatusCode());
        self::assertSame(0, $this->decode($this->request('GET', '/admin/manual-receivables', $org7))['total'] ?? null);
    }

    public function test_viewer_can_read_but_not_create(): void
    {
        $member = $this->tokenFor('member@acme.example');
        $this->request('POST', '/admin/manual-receivables', $member, $this->validBody('V-1'));

        $viewer = $this->tokenFor('viewer@acme.example');
        self::assertSame(200, $this->request('GET', '/admin/manual-receivables', $viewer)->getStatusCode());
        self::assertSame(403, $this->request('POST', '/admin/manual-receivables', $viewer, $this->validBody('V-2'))->getStatusCode());
    }
}
