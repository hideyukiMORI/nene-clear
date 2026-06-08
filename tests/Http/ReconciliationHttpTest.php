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
use NeneClear\InvoiceUpstream\InvoiceItem;
use NeneClear\Receivable\ManualReceivable;
use NeneClear\Receivable\ManualReceivableStatus;
use NeneClear\Receivable\PdoManualReceivableRepository;
use NeneClear\Tests\Support\SchemaFixture;
use NeneClear\User\PdoUserRepository;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ReconciliationHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';
    private const string CSV = "date,deposit,withdrawal,memo\n2026/04/20,110000,,INV-001 payment\n";

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;
    private FakeInvoiceUpstreamClient $invoiceClient;
    private PdoManualReceivableRepository $manualReceivables;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-recon-http-', true) . '.sqlite';
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
        SchemaFixture::createManualReceivables($query);

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

        $this->manualReceivables = new PdoManualReceivableRepository($query);
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
        $token = $this->decode($this->app->handle($request))['token'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private function importCsv(string $token): int
    {
        $file = $this->psr17->createUploadedFile($this->psr17->createStream(self::CSV), strlen(self::CSV), UPLOAD_ERR_OK, 'april.csv', 'text/csv');
        $request = $this->psr17->createServerRequest('POST', '/admin/bank-import-batches')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody(['bank_account_id' => 1]);

        $response = $this->app->handle($request);
        self::assertSame(201, $response->getStatusCode());

        $body = $this->decode($response);
        self::assertArrayHasKey('bank_import_batch_id', $body);

        $txList = $this->decode($this->get($token, '/admin/bank-transactions'));
        $items = $txList['items'] ?? [];
        self::assertNotEmpty($items);

        return (int) $items[0]['bank_transaction_id'];
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

    private function addInvoice(int $id, int $outstandingCents, string $number = ''): void
    {
        $this->invoiceClient->addInvoice(new InvoiceItem(
            invoiceId: $id,
            invoiceNumber: $number !== '' ? $number : 'INV-00' . $id,
            clientId: 100,
            outstandingCents: $outstandingCents,
            totalCents: $outstandingCents,
            dueAt: '2026-12-31',
            status: 'issued',
            currency: 'JPY',
        ));
    }

    public function test_propose_returns_suggestions_for_matching_invoice(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($token);
        $this->addInvoice(1, 110000, 'INV-001'); // exact amount + invoice number in counterparty

        $response = $this->post($token, '/admin/reconciliations/propose', ['bank_transaction_id' => $txId]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertNotEmpty($body['suggestions']);
        self::assertSame(1, $body['suggestions'][0]['invoice_id']);
        self::assertGreaterThan(0.0, $body['suggestions'][0]['score']);
    }

    public function test_confirm_creates_reconciliation_and_marks_tx_matched(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($token);
        $this->addInvoice(1, 110000);

        $response = $this->post($token, '/admin/reconciliations', [
            'bank_transaction_id' => $txId,
            'allocations' => [['invoice_id' => 1, 'amount_cents' => 110000]],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertArrayHasKey('payment_reconciliation_id', $body);
        self::assertSame('confirmed', $body['status']);
        self::assertCount(1, $body['allocations']);

        $txDetail = $this->decode($this->get($token, '/admin/bank-transactions/' . $txId));
        self::assertSame('matched', $txDetail['status']);
    }

    private function seedManualReceivable(int $totalCents): int
    {
        return $this->manualReceivables->save(new ManualReceivable(
            organizationId: 7,
            referenceNumber: 'MR-001',
            clientName: 'カ）アクメ',
            recipientEmail: 'ar@acme.example',
            totalCents: $totalCents,
            outstandingCents: $totalCents,
            currency: 'JPY',
            issuedAt: '2026-03-31',
            dueAt: '2026-04-30',
            status: ManualReceivableStatus::Open,
            createdBy: 1,
            createdAt: '2026-04-01 09:00:00',
        ));
    }

    public function test_confirm_against_a_manual_receivable(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($token); // 110000 deposit

        // A receivable entered directly in Clear (no upstream invoice).
        $mrId = $this->seedManualReceivable(110000);

        $response = $this->post($token, '/admin/reconciliations', [
            'bank_transaction_id' => $txId,
            'allocations' => [['source' => 'manual', 'manual_receivable_id' => $mrId, 'amount_cents' => 110000]],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame('confirmed', $body['status']);
        self::assertCount(1, $body['allocations']);
        self::assertSame('manual', $body['allocations'][0]['source']);
        self::assertSame($mrId, $body['allocations'][0]['manual_receivable_id']);
        self::assertNull($body['allocations'][0]['invoice_id']);
        self::assertNull($body['allocations'][0]['payment_id']);

        // Clear owns the manual receivable's balance — it is now fully paid.
        $mr = $this->decode($this->get($token, '/admin/manual-receivables/' . $mrId));
        self::assertSame('paid', $mr['status']);
        self::assertSame(0, $mr['outstanding_cents']);

        self::assertSame('matched', $this->decode($this->get($token, '/admin/bank-transactions/' . $txId))['status']);
    }

    public function test_propose_includes_manual_receivable_candidates(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($token); // 110000 deposit
        $mrId = $this->seedManualReceivable(110000);

        $body = $this->decode($this->post($token, '/admin/reconciliations/propose', ['bank_transaction_id' => $txId]));

        $manual = array_values(array_filter($body['suggestions'], static fn (array $s): bool => $s['source'] === 'manual'));
        self::assertCount(1, $manual);
        self::assertSame($mrId, $manual[0]['manual_receivable_id']);
    }

    public function test_list_reconciliations(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($token);
        $this->addInvoice(1, 110000);

        $this->post($token, '/admin/reconciliations', [
            'bank_transaction_id' => $txId,
            'allocations' => [['invoice_id' => 1, 'amount_cents' => 110000]],
        ]);

        $response = $this->get($token, '/admin/reconciliations');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $this->decode($response)['total'] ?? null);
    }

    public function test_get_reconciliation_by_id(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($token);
        $this->addInvoice(1, 110000);

        $reconBody = $this->decode($this->post($token, '/admin/reconciliations', [
            'bank_transaction_id' => $txId,
            'allocations' => [['invoice_id' => 1, 'amount_cents' => 110000]],
        ]));
        $reconId = $reconBody['payment_reconciliation_id'];

        $response = $this->get($token, '/admin/reconciliations/' . $reconId);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($reconId, $this->decode($response)['payment_reconciliation_id'] ?? null);
    }

    public function test_reverse_reconciliation(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($token);
        $this->addInvoice(1, 110000);

        $reconId = $this->decode($this->post($token, '/admin/reconciliations', [
            'bank_transaction_id' => $txId,
            'allocations' => [['invoice_id' => 1, 'amount_cents' => 110000]],
        ]))['payment_reconciliation_id'];

        $reverseResponse = $this->post($token, '/admin/reconciliations/' . $reconId . '/reverse', [
            'reversal_reason' => 'wrong invoice',
        ]);
        self::assertSame(200, $reverseResponse->getStatusCode());
        self::assertSame('reversed', $this->decode($reverseResponse)['status']);

        $txDetail = $this->decode($this->get($token, '/admin/bank-transactions/' . $txId));
        self::assertSame('unmatched', $txDetail['status']);
    }

    public function test_viewer_cannot_confirm_match(): void
    {
        $adminToken = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($adminToken);
        $this->addInvoice(1, 110000);

        $viewerToken = $this->tokenFor('viewer@acme.example');
        $response = $this->post($viewerToken, '/admin/reconciliations', [
            'bank_transaction_id' => $txId,
            'allocations' => [['invoice_id' => 1, 'amount_cents' => 110000]],
        ]);

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_viewer_can_list_reconciliations(): void
    {
        $viewerToken = $this->tokenFor('viewer@acme.example');
        $response = $this->get($viewerToken, '/admin/reconciliations');

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_allocation_exceeds_outstanding_returns_422(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($token);
        $this->addInvoice(1, 50000); // only 50k outstanding, tx is 110k

        $response = $this->post($token, '/admin/reconciliations', [
            'bank_transaction_id' => $txId,
            'allocations' => [['invoice_id' => 1, 'amount_cents' => 110000]],
        ]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame(1, $body['invoice_id'] ?? null);
        self::assertSame(50000, $body['outstanding_cents'] ?? null);
    }

    public function test_upstream_unavailable_degrades_to_manual_candidates(): void
    {
        // Invoice upstream is down, but the operator has a manual receivable that
        // matches the deposit — propose must still return it (with a flag), not 503.
        $this->invoiceClient->makeUnavailable();

        $token = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($token); // 110000 deposit
        $this->seedManualReceivable(110000);

        $response = $this->post($token, '/admin/reconciliations/propose', ['bank_transaction_id' => $txId]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertTrue($body['upstream_unavailable']);
        $manual = array_values(array_filter($body['suggestions'], static fn (array $s): bool => $s['source'] === 'manual'));
        self::assertCount(1, $manual);
    }

    public function test_double_reverse_returns_409(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $txId = $this->importCsv($token);
        $this->addInvoice(1, 110000);

        $reconId = $this->decode($this->post($token, '/admin/reconciliations', [
            'bank_transaction_id' => $txId,
            'allocations' => [['invoice_id' => 1, 'amount_cents' => 110000]],
        ]))['payment_reconciliation_id'];

        $this->post($token, '/admin/reconciliations/' . $reconId . '/reverse', ['reversal_reason' => 'first']);

        $response = $this->post($token, '/admin/reconciliations/' . $reconId . '/reverse', ['reversal_reason' => 'second']);
        self::assertSame(409, $response->getStatusCode());
    }

    public function test_list_client_credits(): void
    {
        $viewerToken = $this->tokenFor('viewer@acme.example');
        $response = $this->get($viewerToken, '/admin/client-credits');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->decode($response)['total'] ?? null);
    }
}
