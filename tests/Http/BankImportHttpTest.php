<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use Nene2\Testing\DatabaseTestKit;
use NeneClear\Auth\Role;
use NeneClear\BankImport\AccountType;
use NeneClear\BankImport\BankAccount;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\Http\ApplicationFactory;
use NeneClear\Tests\Support\SchemaFixture;
use NeneClear\User\PdoUserRepository;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class BankImportHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';
    private const string CSV = "date,deposit,withdrawal,memo\n2026/04/20,110000,,ACME\n2026/04/22,50000,,TARO\n";

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-bankhttp-', true) . '.sqlite';
        $query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createUsers($query);
        SchemaFixture::createLoginAttempts($query);
        SchemaFixture::createBankAccounts($query);
        SchemaFixture::createBankImportBatches($query);
        SchemaFixture::createBankTransactions($query);
        SchemaFixture::createAuditEvents($query);

        $users = new PdoUserRepository($query);
        $users->save($this->user('admin@acme.example', Role::Admin));
        $users->save($this->user('viewer@acme.example', Role::Viewer));

        (new PdoBankAccountRepository($query))->save(new BankAccount(
            organizationId: 7,
            bankName: 'Test Bank',
            bankBranch: 'Main',
            accountType: AccountType::Ordinary,
            accountNumber: '123',
            csvEncoding: 'utf8',
            csvDateFormat: 'Y/m/d',
            csvDateColumn: 0,
            csvAmountColumn: 1,
            csvCounterpartyColumn: 3,
            csvHeaderRows: 1,
        ));

        $this->app = ApplicationFactory::create(query: $query, jwtSecret: self::SECRET);
        $this->psr17 = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function user(string $email, Role $role): User
    {
        return new User(
            email: $email,
            role: $role,
            status: UserStatus::Active,
            passwordHash: password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            organizationId: 7,
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

    private function upload(string $token, string $csv, int $bankAccountId = 1): ResponseInterface
    {
        $file = $this->psr17->createUploadedFile($this->psr17->createStream($csv), strlen($csv), UPLOAD_ERR_OK, 'april.csv', 'text/csv');
        $request = $this->psr17->createServerRequest('POST', '/admin/bank-import-batches')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody(['bank_account_id' => $bankAccountId]);

        return $this->app->handle($request);
    }

    private function get(string $token, string $path): ResponseInterface
    {
        return $this->app->handle(
            $this->psr17->createServerRequest('GET', $path)->withHeader('Authorization', 'Bearer ' . $token),
        );
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

    public function test_admin_uploads_and_lists_batches_and_transactions(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $imported = $this->upload($token, self::CSV);
        self::assertSame(201, $imported->getStatusCode());
        self::assertSame(2, $this->decode($imported)['row_count'] ?? null);

        self::assertSame(1, $this->decode($this->get($token, '/admin/bank-import-batches'))['total'] ?? null);
        self::assertSame(2, $this->decode($this->get($token, '/admin/bank-transactions'))['total'] ?? null);
        self::assertSame(2, $this->decode($this->get($token, '/admin/bank-transactions/unmatched'))['total'] ?? null);
    }

    public function test_duplicate_upload_is_blocked(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $this->upload($token, self::CSV);

        self::assertSame(409, $this->upload($token, self::CSV)->getStatusCode());
    }

    public function test_viewer_can_read_but_not_import(): void
    {
        $token = $this->tokenFor('viewer@acme.example');

        self::assertSame(200, $this->get($token, '/admin/bank-transactions')->getStatusCode());

        $denied = $this->upload($token, self::CSV);
        self::assertSame(403, $denied->getStatusCode());
        self::assertStringContainsString('insufficient-capability', (string) $denied->getBody());
    }
}
