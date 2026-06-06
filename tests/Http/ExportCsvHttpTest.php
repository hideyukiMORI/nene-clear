<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use Nene2\Testing\DatabaseTestKit;
use NeneClear\Auth\Role;
use NeneClear\BankImport\AccountType;
use NeneClear\BankImport\BankAccount;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\Http\ApplicationFactory;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceClientInfo;
use NeneClear\InvoiceUpstream\InvoiceItem;
use NeneClear\Tests\Support\SchemaFixture;
use NeneClear\User\PdoUserRepository;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ExportCsvHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';
    private const string CSV = "date,deposit,withdrawal,memo\n2026/04/20,110000,,INV-001\n";

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;
    private FakeInvoiceUpstreamClient $invoiceClient;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-export-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $query = $kit->queryExecutor;

        SchemaFixture::createUsers($query);
        SchemaFixture::createLoginAttempts($query);
        SchemaFixture::createBankAccounts($query);
        SchemaFixture::createBankImportBatches($query);
        SchemaFixture::createBankTransactions($query);
        SchemaFixture::createAuditEvents($query);
        SchemaFixture::createPaymentReconciliations($query);
        SchemaFixture::createReconciliationAllocations($query);
        SchemaFixture::createClientCredits($query);
        SchemaFixture::createClearSettings($query);
        SchemaFixture::createDunningNotices($query);
        SchemaFixture::createDunningPauses($query);

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

        $this->invoiceClient = new FakeInvoiceUpstreamClient();
        $this->app = ApplicationFactory::create(query: $query, transactionManager: $kit->transactionManager, jwtSecret: self::SECRET, invoiceClient: $this->invoiceClient);
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
        $body = json_decode((string) $this->app->handle($request)->getBody(), true);
        self::assertIsString($body['token'] ?? null);

        return $body['token'];
    }

    private function get(string $token, string $path): ResponseInterface
    {
        return $this->app->handle(
            $this->psr17->createServerRequest('GET', $path)
                ->withHeader('Authorization', 'Bearer ' . $token),
        );
    }

    private function post(string $token, string $path, mixed $body): ResponseInterface
    {
        return $this->app->handle(
            $this->psr17->createServerRequest('POST', $path)
                ->withHeader('Authorization', 'Bearer ' . $token)
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody(is_array($body) ? $body : []),
        );
    }

    private function importCsvAndConfirm(): void
    {
        $this->invoiceClient->addInvoice(new InvoiceItem(
            invoiceId: 1,
            invoiceNumber: 'INV-001',
            clientId: 100,
            outstandingCents: 110000,
            totalCents: 110000,
            dueAt: '2026-04-30',
            status: 'issued',
            currency: 'JPY',
        ));
        $this->invoiceClient->addClient(new InvoiceClientInfo(100, 'ACME', 'a@example.com'));

        $token = $this->tokenFor('admin@acme.example');

        $file = $this->psr17->createUploadedFile(
            $this->psr17->createStream(self::CSV),
            strlen(self::CSV),
            UPLOAD_ERR_OK,
            'april.csv',
            'text/csv',
        );
        $importReq = $this->psr17->createServerRequest('POST', '/admin/bank-import-batches')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody(['bank_account_id' => 1]);
        $this->app->handle($importReq);

        $txList = json_decode((string) $this->get($token, '/admin/bank-transactions')->getBody(), true);
        $txId = (int) $txList['items'][0]['bank_transaction_id'];

        $this->post($token, '/admin/reconciliations', [
            'bank_transaction_id' => $txId,
            'allocations' => [['invoice_id' => 1, 'amount_cents' => 110000]],
        ]);
    }

    public function test_export_bank_transactions_returns_csv_with_bom(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $file = $this->psr17->createUploadedFile(
            $this->psr17->createStream(self::CSV),
            strlen(self::CSV),
            UPLOAD_ERR_OK,
            'april.csv',
            'text/csv',
        );
        $this->app->handle(
            $this->psr17->createServerRequest('POST', '/admin/bank-import-batches')
                ->withHeader('Authorization', 'Bearer ' . $token)
                ->withUploadedFiles(['file' => $file])
                ->withParsedBody(['bank_account_id' => 1]),
        );

        $response = $this->get($token, '/admin/export/bank-transactions');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/csv', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('attachment', $response->getHeaderLine('Content-Disposition'));

        $body = (string) $response->getBody();
        self::assertStringStartsWith("\xEF\xBB\xBF", $body, 'Expected UTF-8 BOM');
        self::assertStringContainsString('bank_transaction_id', $body);
        self::assertStringContainsString('110000', $body);
    }

    public function test_export_reconciliations_returns_csv(): void
    {
        $this->importCsvAndConfirm();
        $token = $this->tokenFor('admin@acme.example');

        $response = $this->get($token, '/admin/export/reconciliations');

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringStartsWith("\xEF\xBB\xBF", $body);
        self::assertStringContainsString('reconciliation_id', $body);
        self::assertStringContainsString('110000', $body);
    }

    public function test_export_client_credits_returns_csv_empty_when_none(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $response = $this->get($token, '/admin/export/client-credits');

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringStartsWith("\xEF\xBB\xBF", $body);
        self::assertStringContainsString('client_credit_id', $body);
    }

    public function test_viewer_can_export(): void
    {
        $token = $this->tokenFor('viewer@acme.example');

        $response = $this->get($token, '/admin/export/bank-transactions');
        self::assertSame(200, $response->getStatusCode());
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->app->handle(
            $this->psr17->createServerRequest('GET', '/admin/export/bank-transactions'),
        );
        self::assertSame(401, $response->getStatusCode());
    }

    public function test_export_bank_transactions_date_filter(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $file = $this->psr17->createUploadedFile(
            $this->psr17->createStream(self::CSV),
            strlen(self::CSV),
            UPLOAD_ERR_OK,
            'april.csv',
            'text/csv',
        );
        $this->app->handle(
            $this->psr17->createServerRequest('POST', '/admin/bank-import-batches')
                ->withHeader('Authorization', 'Bearer ' . $token)
                ->withUploadedFiles(['file' => $file])
                ->withParsedBody(['bank_account_id' => 1]),
        );

        $future = $this->get($token, '/admin/export/bank-transactions?date_from=2030-01-01');
        self::assertSame(200, $future->getStatusCode());
        $body = (string) $future->getBody();
        // Header row only (BOM + header); no data rows for future date
        $lines = array_filter(explode("\n", trim($body)));
        self::assertCount(1, $lines, 'Expected header-only CSV for future date filter');
    }
}
