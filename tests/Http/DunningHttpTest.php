<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use Nene2\Testing\DatabaseTestKit;
use NeneClear\Auth\Role;
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

final class DunningHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;
    private FakeInvoiceUpstreamClient $invoiceClient;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-dunning-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $query = $kit->queryExecutor;

        SchemaFixture::createUsers($query);
        SchemaFixture::createLoginAttempts($query);
        SchemaFixture::createBankAccounts($query);
        SchemaFixture::createClearSettings($query);
        SchemaFixture::createAuditEvents($query);
        SchemaFixture::createDunningNotices($query);
        SchemaFixture::createDunningPauses($query);

        $users = new PdoUserRepository($query);
        $users->save($this->user('admin@acme.example', Role::Admin));
        $users->save($this->user('member@acme.example', Role::Member));
        $users->save($this->user('viewer@acme.example', Role::Viewer));

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

    private function post(string $token, string $path, mixed $body = []): ResponseInterface
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

    private function addOpenInvoice(int $id = 1): void
    {
        $this->invoiceClient->addInvoice(new InvoiceItem(
            invoiceId: $id,
            invoiceNumber: 'INV-00' . $id,
            clientId: 100,
            outstandingCents: 110000,
            totalCents: 110000,
            dueAt: '2026-04-30',
            status: 'issued',
            currency: 'JPY',
        ));
        $this->invoiceClient->addClient(new InvoiceClientInfo(
            clientId: 100,
            contactName: 'ACME Corp',
            recipientEmail: 'accounts@acme.example',
        ));
    }

    public function test_admin_sends_dunning_returns_201(): void
    {
        $this->addOpenInvoice();
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->post($token, '/admin/dunning-notices', ['invoice_id' => 1]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertArrayHasKey('dunning_notice_id', $body);
        self::assertSame(1, $body['invoice_id']);
        self::assertSame('accounts@acme.example', $body['recipient_email']);
        self::assertSame('log', $body['channel']);
    }

    public function test_member_can_send_dunning(): void
    {
        $this->addOpenInvoice();
        $token = $this->tokenFor('member@acme.example');
        $response = $this->post($token, '/admin/dunning-notices', ['invoice_id' => 1]);

        self::assertSame(201, $response->getStatusCode());
    }

    public function test_viewer_cannot_send_dunning(): void
    {
        $this->addOpenInvoice();
        $token = $this->tokenFor('viewer@acme.example');
        $response = $this->post($token, '/admin/dunning-notices', ['invoice_id' => 1]);

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_viewer_can_list_dunning_notices(): void
    {
        $token = $this->tokenFor('viewer@acme.example');
        $response = $this->get($token, '/admin/dunning-notices');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->decode($response)['total'] ?? null);
    }

    public function test_list_and_get_by_id(): void
    {
        $this->addOpenInvoice();
        $token = $this->tokenFor('admin@acme.example');
        $noticeId = $this->decode($this->post($token, '/admin/dunning-notices', ['invoice_id' => 1]))['dunning_notice_id'];

        $list = $this->decode($this->get($token, '/admin/dunning-notices'));
        self::assertSame(1, $list['total']);

        $detail = $this->decode($this->get($token, '/admin/dunning-notices/' . $noticeId));
        self::assertSame($noticeId, $detail['dunning_notice_id']);
    }

    public function test_paid_invoice_returns_422(): void
    {
        $this->invoiceClient->addInvoice(new InvoiceItem(1, 'INV-001', 100, 0, 110000, '2026-04-30', 'paid', 'JPY'));
        $this->invoiceClient->addClient(new InvoiceClientInfo(100, 'ACME', 'a@example.com'));

        $token = $this->tokenFor('admin@acme.example');
        $response = $this->post($token, '/admin/dunning-notices', ['invoice_id' => 1]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function test_too_frequent_returns_422_with_next_allowed_at(): void
    {
        $this->addOpenInvoice();
        $token = $this->tokenFor('admin@acme.example');
        $this->post($token, '/admin/dunning-notices', ['invoice_id' => 1]);

        $response = $this->post($token, '/admin/dunning-notices', ['invoice_id' => 1]);
        self::assertSame(422, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertArrayHasKey('next_allowed_at', $body);
    }

    public function test_upstream_unavailable_returns_503(): void
    {
        $this->invoiceClient->makeUnavailable();
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->post($token, '/admin/dunning-notices', ['invoice_id' => 1]);

        self::assertSame(503, $response->getStatusCode());
    }

    public function test_get_wrong_org_returns_404(): void
    {
        $this->addOpenInvoice();
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->get($token, '/admin/dunning-notices/9999');

        self::assertSame(404, $response->getStatusCode());
    }

    // --- Dunning pause / resume ---

    public function test_pause_creates_record_and_blocks_send(): void
    {
        $this->addOpenInvoice();
        $token = $this->tokenFor('admin@acme.example');

        $pauseResponse = $this->post($token, '/admin/dunning-pauses', [
            'invoice_id' => 1,
            'reason' => 'awaiting credit note',
        ]);
        self::assertSame(201, $pauseResponse->getStatusCode());
        $pause = $this->decode($pauseResponse);
        self::assertSame(1, $pause['invoice_id']);
        self::assertSame('awaiting credit note', $pause['paused_reason']);

        $sendResponse = $this->post($token, '/admin/dunning-notices', ['invoice_id' => 1]);
        self::assertSame(422, $sendResponse->getStatusCode());
        $body = $this->decode($sendResponse);
        self::assertStringContainsString('dunning-paused', $body['type']);
    }

    public function test_resume_allows_send(): void
    {
        $this->addOpenInvoice();
        $token = $this->tokenFor('admin@acme.example');

        $this->post($token, '/admin/dunning-pauses', ['invoice_id' => 1, 'reason' => 'hold']);

        $resumeResponse = $this->post($token, '/admin/dunning-pauses/1/resume', []);
        self::assertSame(204, $resumeResponse->getStatusCode());

        $sendResponse = $this->post($token, '/admin/dunning-notices', ['invoice_id' => 1]);
        self::assertSame(201, $sendResponse->getStatusCode());
    }

    public function test_list_pauses_active_only(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $this->post($token, '/admin/dunning-pauses', ['invoice_id' => 1, 'reason' => 'hold 1']);
        $this->post($token, '/admin/dunning-pauses', ['invoice_id' => 2, 'reason' => 'hold 2']);
        $this->post($token, '/admin/dunning-pauses/1/resume', []);

        $response = $this->get($token, '/admin/dunning-pauses?active_only=true');
        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame(1, $body['total']);
        self::assertSame(2, $body['items'][0]['invoice_id']);
    }

    public function test_viewer_cannot_pause(): void
    {
        $token = $this->tokenFor('viewer@acme.example');
        $response = $this->post($token, '/admin/dunning-pauses', ['invoice_id' => 1, 'reason' => 'x']);

        self::assertSame(403, $response->getStatusCode());
    }
}
