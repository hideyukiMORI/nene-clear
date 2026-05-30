<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use Nene2\Testing\DatabaseTestKit;
use NeneClear\Auth\Role;
use NeneClear\Http\ApplicationFactory;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\Tests\Support\SchemaFixture;
use NeneClear\User\PdoUserRepository;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ClearSettingsHttpTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery';

    private string $dbPath;
    private RequestHandlerInterface $app;
    private Psr17Factory $psr17;
    private FakeInvoiceUpstreamClient $invoiceClient;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-settings-', true) . '.sqlite';
        $query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;

        SchemaFixture::createUsers($query);
        SchemaFixture::createBankAccounts($query);
        SchemaFixture::createClearSettings($query);
        SchemaFixture::createAuditEvents($query);

        $users = new PdoUserRepository($query);
        $users->save($this->user('admin@acme.example', Role::Admin));
        $users->save($this->user('viewer@acme.example', Role::Viewer));

        $this->invoiceClient = new FakeInvoiceUpstreamClient();
        $this->app = ApplicationFactory::create(query: $query, jwtSecret: self::SECRET, invoiceClient: $this->invoiceClient);
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

    private function get(string $token, string $path): ResponseInterface
    {
        return $this->app->handle(
            $this->psr17->createServerRequest('GET', $path)->withHeader('Authorization', 'Bearer ' . $token),
        );
    }

    private function put(string $token, string $path, mixed $body): ResponseInterface
    {
        return $this->app->handle(
            $this->psr17->createServerRequest('PUT', $path)
                ->withHeader('Authorization', 'Bearer ' . $token)
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody(is_array($body) ? $body : []),
        );
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

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);

        return $data;
    }

    public function test_get_returns_defaults_when_no_settings_saved(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->get($token, '/admin/clear-settings');

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame(7, $body['dunning_min_interval_days']);
        self::assertSame('', $body['upstream_base_url']);
        self::assertSame([], $body['bank_accounts']);
    }

    public function test_put_saves_and_get_reflects_update(): void
    {
        $token = $this->tokenFor('admin@acme.example');

        $response = $this->put($token, '/admin/clear-settings', [
            'upstream_base_url' => 'https://invoice.example.com',
            'upstream_token_ref' => 'NENE_INVOICE_TOKEN',
            'dunning_min_interval_days' => 14,
            'bank_accounts' => [
                [
                    'bank_name' => 'Test Bank',
                    'bank_branch' => 'Main',
                    'account_type' => 'ordinary',
                    'account_number' => '1234567',
                    'csv_encoding' => 'utf8',
                    'csv_date_format' => 'Y/m/d',
                    'csv_date_column' => 0,
                    'csv_amount_column' => 1,
                    'csv_counterparty_column' => 3,
                    'csv_header_rows' => 1,
                ],
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame('https://invoice.example.com', $body['upstream_base_url']);
        self::assertSame(14, $body['dunning_min_interval_days']);
        self::assertCount(1, $body['bank_accounts']);
        self::assertSame('Test Bank', $body['bank_accounts'][0]['bank_name']);

        $getBody = $this->decode($this->get($token, '/admin/clear-settings'));
        self::assertSame(14, $getBody['dunning_min_interval_days']);
        self::assertCount(1, $getBody['bank_accounts']);
    }

    public function test_viewer_cannot_get_clear_settings(): void
    {
        $token = $this->tokenFor('viewer@acme.example');
        $response = $this->get($token, '/admin/clear-settings');

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_test_upstream_reachable(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->post($token, '/admin/clear-settings/test-upstream');

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->decode($response)['reachable'] ?? false);
    }

    public function test_test_upstream_unreachable(): void
    {
        $this->invoiceClient->makeUnavailable();
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->post($token, '/admin/clear-settings/test-upstream');

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($this->decode($response)['reachable'] ?? true);
    }

    public function test_invalid_dunning_interval_returns_422(): void
    {
        $token = $this->tokenFor('admin@acme.example');
        $response = $this->put($token, '/admin/clear-settings', ['dunning_min_interval_days' => 0]);

        self::assertSame(422, $response->getStatusCode());
    }
}
