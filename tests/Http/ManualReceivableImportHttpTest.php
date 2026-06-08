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

final class ManualReceivableImportHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';

    private const string CSV = "reference_number,client_name,total_cents,due_at,recipient_email\n"
        . "REF-1,カ）アクメ,110000,2026-04-30,ar@acme.example\n"
        . "REF-2,カ）サクラ,50000,2026-05-31,\n"
        . "REF-1,カ）アクメ,110000,2026-04-30,ar@acme.example\n"
        . "BAD-1,,abc,,\n";

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-mrimp-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $query = $kit->queryExecutor;
        SchemaFixture::createUsers($query);
        SchemaFixture::createLoginAttempts($query);
        SchemaFixture::createAuditEvents($query);
        SchemaFixture::createManualReceivables($query);

        $users = new PdoUserRepository($query);
        $users->save($this->user('member@acme.example', Role::Member, 7));
        $users->save($this->user('viewer@acme.example', Role::Viewer, 7));

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

    private function upload(string $token, string $csv): ResponseInterface
    {
        $file = $this->psr17->createUploadedFile($this->psr17->createStream($csv), strlen($csv), UPLOAD_ERR_OK, 'receivables.csv', 'text/csv');
        $request = $this->psr17->createServerRequest('POST', '/admin/manual-receivable-imports')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withUploadedFiles(['file' => $file]);

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

    public function test_imports_valid_rows_skips_duplicates_and_reports_errors(): void
    {
        $token = $this->tokenFor('member@acme.example');

        $result = $this->upload($token, self::CSV);
        self::assertSame(200, $result->getStatusCode());
        $body = $this->decode($result);
        self::assertSame(2, $body['created']);   // REF-1, REF-2
        self::assertSame(1, $body['skipped']);   // REF-1 duplicate
        self::assertIsArray($body['errors']);
        self::assertCount(1, $body['errors']);   // BAD-1 (empty client, non-numeric total)
        self::assertSame(5, $body['errors'][0]['row'] ?? null); // 1-based CSV line

        $list = $this->decode($this->app->handle(
            $this->psr17->createServerRequest('GET', '/admin/manual-receivables')->withHeader('Authorization', 'Bearer ' . $token),
        ));
        self::assertSame(2, $list['total'] ?? null);
    }

    public function test_missing_required_header_is_rejected(): void
    {
        $token = $this->tokenFor('member@acme.example');
        // No total_cents column.
        $csv = "reference_number,client_name\nREF-9,カ）アクメ\n";
        self::assertSame(422, $this->upload($token, $csv)->getStatusCode());
    }

    public function test_viewer_cannot_import(): void
    {
        $token = $this->tokenFor('viewer@acme.example');
        self::assertSame(403, $this->upload($token, self::CSV)->getStatusCode());
    }
}
